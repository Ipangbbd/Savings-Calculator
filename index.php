<?php
require 'db.php';

// Handle form submissions
$message = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new transaction
    if (isset($_POST['add_transaction'])) {
        $description = trim($_POST['description']);
        $amount = floatval($_POST['amount']);
        $type = $_POST['type'];
        $category = $_POST['category'];
        $date = $_POST['date'];

        // Basic validation
        if (empty($description) || $amount <= 0 || empty($date)) {
            $message = "Please fill all fields with valid values.";
            $alertType = "danger";
        } else {
            $stmt = $conn->prepare("INSERT INTO transactions (description, amount, type, category, date) 
                                    VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sdsss", $description, $amount, $type, $category, $date);

            if ($stmt->execute()) {
                $message = "Transaction added successfully!";
                $alertType = "success";

                header("Location: " . $_SERVER['PHP_SELF'] . "?message=" . urlencode($message) . "&type=" . $alertType);
                exit;
            } else {
                $message = "Error: " . $stmt->error;
                $alertType = "danger";
            }
            $stmt->close();
        }
    }

    // Delete transaction
    elseif (isset($_POST['delete_transaction'])) {
        $id = intval($_POST['transaction_id']);

        $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "Transaction deleted successfully!";
            $alertType = "success";
        } else {
            $message = "Error: " . $stmt->error;
            $alertType = "danger";
        }
        $stmt->close();

        header("Location: " . $_SERVER['PHP_SELF'] . "?message=" . urlencode($message) . "&type=" . $alertType);
        exit;
    }

    // Clear all data
    elseif (isset($_POST['clear_data'])) {
        if ($conn->query("TRUNCATE TABLE transactions")) {
            $message = "All data has been reset.";
            $alertType = "warning";
        } else {
            $message = "Error: " . $conn->error;
            $alertType = "danger";
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?message=" . urlencode($message) . "&type=" . $alertType);
        exit;
    }
}

// Get transactions from database
function getTransactions($conn)
{
    $transactions = [];
    $result = $conn->query("SELECT * FROM transactions ORDER BY date DESC");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }

    return $transactions;
}

// Calculate totals
function calculateTotals($transactions)
{
    $savings = 0;
    $expenses = 0;

    foreach ($transactions as $transaction) {
        if ($transaction['type'] === 'savings') {
            $savings += $transaction['amount'];
        } else {
            $expenses += $transaction['amount'];
        }
    }

    return [
        'savings' => $savings,
        'expenses' => $expenses,
        'balance' => $savings - $expenses
    ];
}

// Format currency
function formatCurrency($amount)
{
    return number_format($amount, 0, ',', '.');
}

// Format date
function formatDate($dateString)
{
    $date = new DateTime($dateString);
    return $date->format('d M Y');
}

// Get transaction categories
function getCategories()
{
    return [
        'salary' => 'Salary',
        'bonus' => 'Bonus',
        'food' => 'Food',
        'transport' => 'Transport',
        'entertainment' => 'Entertainment',
        'utilities' => 'Utilities',
        'rent' => 'Rent',
        'shopping' => 'Shopping',
        'healthcare' => 'Healthcare',
        'education' => 'Education',
        'other' => 'Other'
    ];
}

// Get transactions and calculate totals
$transactions = getTransactions($conn);
$totals = calculateTotals($transactions);
$categories = getCategories();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Budget Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-wallet me-2"></i>Smart Budget Tracker
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        Total Savings
                        <span class="card-icon savings-icon"><i class="fas fa-piggy-bank"></i></span>
                    </div>
                    <div class="card-body">
                        <h3 class="savings-total">Rp <?php echo formatCurrency($totals['savings']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        Total Expenses
                        <span class="card-icon expense-icon"><i class="fas fa-shopping-cart"></i></span>
                    </div>
                    <div class="card-body">
                        <h3 class="expenses-total">Rp <?php echo formatCurrency($totals['expenses']); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        Balance
                        <span class="card-icon balance-icon"><i class="fas fa-chart-line"></i></span>
                    </div>
                    <div class="card-body">
                        <h3 class="balance-total">Rp <?php echo formatCurrency($totals['balance']); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Add Transaction Form -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-plus-circle me-2"></i>Add New Transaction
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="transaction-form">
                            <div class="mb-3">
                                <label for="description" class="form-label">What's this for?</label>
                                <input type="text" class="form-control" id="description" name="description" placeholder="E.g., Grocery shopping, Monthly salary">
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">How much?</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="amount" name="amount" placeholder="Enter amount">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="type" class="form-label">Money in or out?</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="savings">Money In (Income)</option>
                                    <option value="expense">Money Out (Expense)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <?php foreach ($categories as $value => $label): ?>
                                        <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="date" class="form-label">When?</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" d>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="add_transaction" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Transaction
                                </button>
                                <button type="submit" name="clear_data" class="btn btn-warning" onclick="return confirm('Are you sure you want to reset all data? This cannot be undone.')">
                                    <i class="fas fa-trash-alt me-2"></i>Reset All Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Transaction Table -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history me-2"></i>Recent Transactions
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-header">
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                        <tr>
                                            <td colspan="6" class="empty-table-message">No transactions found. Add your first transaction!</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo formatDate($transaction['date']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                <td>
                                                    <?php
                                                    $categoryIcons = [
                                                        'salary' => 'fa-money-bill-wave',
                                                        'bonus' => 'fa-gift',
                                                        'food' => 'fa-utensils',
                                                        'transport' => 'fa-car',
                                                        'entertainment' => 'fa-film',
                                                        'utilities' => 'fa-bolt',
                                                        'rent' => 'fa-home',
                                                        'shopping' => 'fa-shopping-bag',
                                                        'healthcare' => 'fa-heartbeat',
                                                        'education' => 'fa-graduation-cap',
                                                        'other' => 'fa-question-circle'
                                                    ];
                                                    $icon = isset($categoryIcons[$transaction['category']]) ? $categoryIcons[$transaction['category']] : 'fa-tag';
                                                    $categoryName = isset($categories[$transaction['category']]) ? $categories[$transaction['category']] : ucfirst($transaction['category']);
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?> category-icon"></i>
                                                    <?php echo $categoryName; ?>
                                                </td>
                                                <td>
                                                    <span class="type-badge <?php echo $transaction['type'] === 'savings' ? 'savings-badge' : 'expense-badge'; ?>">
                                                        <?php echo $transaction['type'] === 'savings' ? 'Income' : 'Expense'; ?>
                                                    </span>
                                                </td>
                                                <td>Rp <?php echo formatCurrency($transaction['amount']); ?></td>
                                                <td>
                                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display: inline;">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                        <button type="submit" name="delete_transaction" class="btn btn-link p-0 delete-btn" onclick="return confirm('Are you sure you want to delete this transaction?')">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-5">
        <div class="container">
            <p class="mb-0 text-center">&copy; 2025 Smart Budget Tracker By Ali. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php
// Close database connection
$conn->close();
?>