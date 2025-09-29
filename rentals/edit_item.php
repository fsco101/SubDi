<?php
ob_start();
include '../includes/header.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Item ID not provided.");
}

$id = intval($_GET['id']); // Ensure it's an integer

// Fetch item details
try {
    $stmt = $conn->prepare("SELECT * FROM rental_items WHERE item_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    if (!$item) {
        die("Error: Item not found.");
    }
} catch (mysqli_sql_exception $e) {
    die("Error fetching item: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['rental_price']);
    $quantity = intval($_POST['quantity']); // Get quantity from form
    $imagePath = $item['image_path']; // Keep old image by default

    // Validate quantity
    if ($quantity < 1) {
        die("Error: Quantity must be at least 1.");
    }

    // Handle image upload if a new file is provided
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = __DIR__ . DIRECTORY_SEPARATOR . 'item_upload' . DIRECTORY_SEPARATOR;
        $fileName = basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');
        if (in_array($fileType, $allowTypes)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $imagePath = 'item_upload/' . $fileName; // Store relative path
            } else {
                die("Error: Failed to upload new image.");
            }
        } else {
            die("Error: Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.");
        }
    }

    // Update item in the database
    try {
        $stmt = $conn->prepare("UPDATE rental_items SET name = ?, description = ?, rental_price = ?, quantity = ?, image_path = ? WHERE item_id = ?");
        $stmt->bind_param("ssdisi", $name, $description, $price, $quantity, $imagePath, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: index_item.php");
        exit;
    } catch (mysqli_sql_exception $e) {
        die("Error updating item: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Item</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
</head>
<body>
    <h1>Edit Item</h1>
    <form action="edit_item.php?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data">
        <label for="name">Name:</label>
        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($item['name']); ?>" required>
        <br>
        <label for="description">Description:</label>
        <textarea name="description" id="description" required><?php echo htmlspecialchars($item['description']); ?></textarea>
        <br>
        <label for="rental_price">Price:</label>
        <input type="number" name="rental_price" id="rental_price" step="0.01" value="<?php echo htmlspecialchars($item['rental_price']); ?>" required>
        <br>
        <label for="quantity">Quantity:</label>
        <input type="number" name="quantity" id="quantity" min="1" value="<?php echo htmlspecialchars($item['quantity']); ?>" required>
        <br>
        <label for="image">Current Image:</label>
        <br>
        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" width="100" alt="Current Image">
        <br>
        <label for="image">Change Image:</label>
        <input type="file" name="image" id="image" accept="image/*">
        <br>
        <input type="submit" value="Update Item">
    </form>
</body>
</html>



<script> 
                const PageActions = {
                    // Configuration
                    config: {
                        reloadDelay: 800, // Delay in ms before page reload (allows for visual feedback)
                        showLoadingIndicator: true, // Whether to show loading indicator during actions
                        confirmationRequired: true, // Whether to show confirmation dialogs for destructive actions
                    },

                    // Initialize functionality
                    init: function() {
                        this.setupFormSubmissions();
                        this.setupActionButtons();
                        this.setupConfirmationDialogs();
                        
                        // Add loading indicator to body if enabled
                        if (this.config.showLoadingIndicator) {
                            this.createLoadingIndicator();
                        }
                        
                        console.log("PageActions initialized");
                    },
                    
                    // Handle all form submissions
                    setupFormSubmissions: function() {
                        document.querySelectorAll('form').forEach(form => {
                            form.addEventListener('submit', (e) => {
                                // Skip for forms with data-no-reload attribute
                                if (form.hasAttribute('data-no-reload')) return;
                                
                                // For normal forms, show loading indicator after submit
                                if (!e.defaultPrevented) {
                                    this.showLoading();
                                }
                            });
                        });
                    },
                    
                    // Setup action buttons (any button with data-action attribute)
                    setupActionButtons: function() {
                        document.querySelectorAll('[data-action]').forEach(button => {
                            button.addEventListener('click', (e) => {
                                const action = button.dataset.action;
                                
                                // Handle different action types
                                switch (action) {
                                    case 'reload':
                                        this.reloadPage();
                                        break;
                                    case 'back':
                                        history.back();
                                        break;
                                    case 'submit-form':
                                        const formId = button.dataset.formId;
                                        if (formId) {
                                            document.getElementById(formId).submit();
                                        }
                                        break;
                                    default:
                                        // For custom actions, check if there's a reload parameter
                                        if (button.hasAttribute('data-reload')) {
                                            this.reloadPage(button.dataset.message);
                                        }
                                }
                            });
                        });
                    },
                    
                    // Setup confirmation dialogs for any element with data-confirm attribute
                    setupConfirmationDialogs: function() {
                        document.querySelectorAll('[data-confirm]').forEach(element => {
                            element.addEventListener('click', (e) => {
                                if (!this.config.confirmationRequired) return;
                                
                                const message = element.dataset.confirm || 'Are you sure you want to proceed?';
                                
                                if (!confirm(message)) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    return false;
                                } else {
                                    this.showLoading();
                                    
                                    // If there's a reload attribute, reload after delay
                                    if (element.hasAttribute('data-reload')) {
                                        e.preventDefault();
                                        setTimeout(() => {
                                            window.location.reload();
                                        }, this.config.reloadDelay);
                                    }
                                }
                            });
                        });
                    },
                    
                    // Create loading indicator element
                    createLoadingIndicator: function() {
                        const loadingEl = document.createElement('div');
                        loadingEl.id = 'page-loading-indicator';
                        loadingEl.innerHTML = `
                            <div class="loading-overlay">
                                <div class="loading-spinner"></div>
                                <div class="loading-message">Processing...</div>
                            </div>
                        `;
                        loadingEl.style.cssText = `
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background-color: rgba(0, 0, 0, 0.5);
                            display: none;
                            justify-content: center;
                            align-items: center;
                            z-index: 9999;
                        `;
                        
                        const spinner = document.createElement('style');
                        spinner.textContent = `
                            .loading-overlay {
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                padding: 20px;
                                background-color: white;
                                border-radius: 8px;
                                box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
                            }
                            .loading-spinner {
                                width: 40px;
                                height: 40px;
                                border: 4px solid #f3f3f3;
                                border-top: 4px solid #3498db;
                                border-radius: 50%;
                                animation: spin 1s linear infinite;
                                margin-bottom: 10px;
                            }
                            .loading-message {
                                color: #333;
                                font-family: sans-serif;
                            }
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                        `;
                        
                        document.head.appendChild(spinner);
                        document.body.appendChild(loadingEl);
                    },
                    
                    // Show loading indicator
                    showLoading: function(message = "Processing...") {
                        if (!this.config.showLoadingIndicator) return;
                        
                        const loader = document.getElementById('page-loading-indicator');
                        if (loader) {
                            const messageEl = loader.querySelector('.loading-message');
                            if (messageEl) messageEl.textContent = message;
                            
                            loader.style.display = 'flex';
                        }
                    },
                    
                    // Hide loading indicator
                    hideLoading: function() {
                        if (!this.config.showLoadingIndicator) return;
                        
                        const loader = document.getElementById('page-loading-indicator');
                        if (loader) {
                            loader.style.display = 'none';
                        }
                    },
                    
                    // Reload the page with optional message
                    reloadPage: function(message = null) {
                        if (message) this.showLoading(message);
                        else this.showLoading("Reloading page...");
                        
                        setTimeout(() => {
                            window.location.reload();
                        }, this.config.reloadDelay);
                    }
                };

                // Initialize when DOM is ready
                document.addEventListener('DOMContentLoaded', () => {
                    PageActions.init();
                });

                // Add utility for any action buttons added dynamically 
                window.reloadPage = function() {
                    PageActions.reloadPage();
                };
                </script>
                <?php include '../includes/footer.php'; ?>