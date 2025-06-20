<?php
session_start();
require_once '../connection/db_connection.php';

// Check if user is logged in and is a staff member
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit();
}

// Get vendors list
$stmt = $conn->prepare("
    SELECT v.id, u.username as name
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    WHERE v.approval_status = 'approved'
    ORDER BY u.username
");
$stmt->execute();
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected vendor's menu items
$selected_vendor_id = $_GET['vendor_id'] ?? null;
$menu_items = [];

if ($selected_vendor_id) {
    $stmt = $conn->prepare("
        SELECT mi.*, c.name as category_name
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mi.item_id = mc.menu_item_id
        LEFT JOIN categories c ON mc.category_id = c.id
        WHERE mi.vendor_id = ? AND mi.is_available = 1
        ORDER BY c.name, mi.name
    ");
    $stmt->execute([$selected_vendor_id]);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Menu';
ob_start();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Menu</h1>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <!-- Vendor Selection -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Select Vendor</h3>
            </div>
            <div class="card-body">
                <form method="get" class="form-inline">
                    <select name="vendor_id" class="form-control mr-2" onchange="this.form.submit()">
                        <option value="">-- Select Vendor --</option>
                        <?php foreach ($vendors as $vendor): ?>
                            <option value="<?php echo $vendor['id']; ?>" 
                                    <?php echo $selected_vendor_id == $vendor['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vendor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Menu Items -->
        <div class="row">
            <?php if (!empty($menu_items)): ?>
                <?php foreach ($menu_items as $item): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if (!empty($item['image'])): ?>
                                <img src="../uploads/menu_items/<?php echo htmlspecialchars($item['image']); ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     style="height: 200px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                                <?php if (!empty($item['category_name'])): ?>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                <?php endif; ?>
                                <p class="card-text mt-2"><?php echo htmlspecialchars($item['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">₹<?php echo number_format($item['price'], 2); ?></h6>
                                    <button onclick="addToCart(<?php echo $item['item_id']; ?>)" 
                                            class="btn btn-primary btn-sm">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <?php if (!$selected_vendor_id): ?>
                            Please select a vendor to view their menu.
                        <?php else: ?>
                            No menu items available for this vendor.
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function addToCart(itemId) {
    fetch('../staff/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `menu_item_id=${itemId}&quantity=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: data.message,
                showCancelButton: true,
                confirmButtonText: 'View Cart',
                cancelButtonText: 'Continue Shopping'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'cart.php';
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.error
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to add item to cart'
        });
    });
}
</script>

<?php
$content = ob_get_clean();
require_once '../includes/layout.php';
?> 