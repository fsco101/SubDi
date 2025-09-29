<?php
include '../includes/header.php';

if (!isset($_GET['id'])) {
    die("Invalid property ID.");
}

$property_id = $_GET['id'];
$query = "SELECT * FROM properties WHERE property_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    die("Property not found.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $type = $_POST['type'];
    $status = $_POST['status'];

    // Handle image upload
    $imagePath = $property['image_url']; // Keep existing image
    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "../uploads/property/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true); // Ensure directory exists
        }

        $fileName = time() . "_" . basename($_FILES["image"]["name"]); // Unique filename
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($fileType), $allowedTypes)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $imagePath = $targetFilePath;
            } else {
                $error = "Error uploading the image.";
            }
        } else {
            $error = "Invalid file type. Only JPG, JPEG, PNG & GIF allowed.";
        }
    }

    if (!empty($_FILES["images"]["name"][0])) {
        $targetDir = "../uploads/property/";
        foreach ($_FILES["images"]["name"] as $key => $name) {
            $fileName = time() . "_" . basename($name);
            $targetFilePath = $targetDir . $fileName;
            $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);

            if (in_array(strtolower($fileType), ['jpg', 'jpeg', 'png', 'gif'])) {
                if (move_uploaded_file($_FILES["images"]["tmp_name"][$key], $targetFilePath)) {
                    $conn->query("INSERT INTO property_images (property_id, image_url) VALUES ($property_id, '$targetFilePath')");
                }
            }
        }
    }

    // Update property details
    $updateQuery = "UPDATE properties SET title=?, description=?, price=?, type=?, status=?, image_url=? WHERE property_id=?";
    $stmt = $conn->prepare($updateQuery);

    if (!$stmt) {
        die("Error preparing the statement: " . $conn->error);
    }

    $stmt->bind_param("ssdsssi", $title, $description, $price, $type, $status, $imagePath, $property_id);

    if ($stmt->execute()) {
        echo "<script>alert('Property updated successfully!'); window.location.href='index_properties.php';</script>";
        exit();
    } else {
        $error = "Error updating property: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Property | Subdivision Management System</title>
    <link rel="stylesheet" href="/subdisystem/style/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --border-radius: 0.5rem;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.08);
            --primary-color: #3b82f6;
            --text-color: #334155;
            --bg-light: #f8f9fa;
        }

        body {
            background: linear-gradient(to right, var(--bg-light), #e9ecef);
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
            padding-bottom: 2rem;
            padding-top: 2rem;
            margin: 0;
        }

        .container {
            max-width: 100%;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 1px solid #e2e8f0;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            transition: all 0.2s;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
            outline: none;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .image-preview {
            margin-top: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            padding: 1rem;
            text-align: center;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 0.375rem;
        }

        .no-image {
            background: #f8fafc;
            color: #94a3b8;
            padding: 2rem;
            border-radius: 0.375rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #2563eb);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2);
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            border: none;
            padding: 0.75rem 1.5rem;
            color: #475569;
            font-weight: 500;
            border-radius: var(--border-radius);
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
        }

        .file-input {
            margin-top: 1rem;
        }

        .file-input-label {
            display: block;
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-input-label:hover {
            border-color: #94a3b8;
        }

        .file-input-label i {
            display: block;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        .file-input input[type="file"] {
            position: absolute;
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: capitalize;
        }

        .badge-house {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-apartment {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-lot {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-available {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-sold {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .badge-for-rent {
            background-color: #e0e7ff;
            color: #3730a3;
        }

        /* Equal width columns */
        .equal-width {
            display: flex;
            flex-wrap: wrap;
        }

        .equal-width > div {
            flex: 1;
            padding: 0.75rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                margin-top: 1rem;
                margin-bottom: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="section-header">
            <h1 class="section-title">
                <i class="bi bi-pencil-square"></i> Edit Property
            </h1>
            <a href="index_properties.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Properties
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="propertyForm">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="title" class="form-label">Property Title</label>
                        <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($property['title']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?= htmlspecialchars($property['description']); ?></textarea>
                    </div>

                    <div class="equal-width">
                        <div class="form-group">
                            <label for="price" class="form-label">Price (PHP)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" id="price" name="price" value="<?= htmlspecialchars($property['price']); ?>" step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="type" class="form-label">Property Type</label>
                            <select class="form-select form-control" id="type" name="type" required>
                                <option value="house" <?= ($property['type'] == 'house') ? 'selected' : ''; ?>>House</option>
                                <option value="apartment" <?= ($property['type'] == 'apartment') ? 'selected' : ''; ?>>Apartment</option>
                                <option value="lot" <?= ($property['type'] == 'lot') ? 'selected' : ''; ?>>Lot</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select form-control" id="status" name="status" required>
                                <option value="available" <?= ($property['status'] == 'available') ? 'selected' : ''; ?>>For Sale</option>
                                <option value="sold" <?= ($property['status'] == 'sold') ? 'selected' : ''; ?>>Sold</option>
                                <option value="For rent" <?= ($property['status'] == 'For rent') ? 'selected' : ''; ?>>For Rent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label class="form-label">Current Image</label>
                        <div class="image-preview">
                            <?php if (!empty($property['image_url'])): ?>
                                <img src="<?= htmlspecialchars($property['image_url']); ?>" alt="Property Image" class="img-fluid">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="bi bi-image fs-1 d-block mb-2"></i>
                                    No image available
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="file-input">
                            <label for="image" class="file-input-label">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <span>Choose new image</span>
                                <small class="d-block text-muted mt-2">JPG, PNG or GIF (max 5MB)</small>
                            </label>
                            <input type="file" id="image" name="image" accept="image/*">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Upload Additional Images</label>
                        <input type="file" name="images[]" class="form-control" multiple>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-primary" data-confirm="Are you sure you want to update this property?">
                    <i class="bi bi-save"></i> Save Changes
                </button>
                <a href="index_properties.php" class="btn btn-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show filename when file is selected
        document.getElementById('image').addEventListener('change', function() {
            const fileName = this.files[0]?.name;
            if (fileName) {
                const label = document.querySelector('.file-input-label span');
                label.textContent = fileName;
            }
        });

        /**
         * Page Action Handler - Global utility for managing page actions and reloads
         */
        const PageActions = {
            // Configuration
            config: {
                reloadDelay: 800,
                showLoadingIndicator: true,
                confirmationRequired: true,
            },

            // Initialize functionality
            init: function() {
                this.setupFormSubmissions();
                this.setupActionButtons();
                this.setupConfirmationDialogs();

                if (this.config.showLoadingIndicator) {
                    this.createLoadingIndicator();
                }
            },

            // Handle form submissions
            setupFormSubmissions: function() {
                document.querySelectorAll('form').forEach(form => {
                    form.addEventListener('submit', (e) => {
                        if (form.hasAttribute('data-no-reload')) return;

                        if (!e.defaultPrevented) {
                            this.showLoading("Saving property changes...");
                        }
                    });
                });
            },

            // Set up action buttons
            setupActionButtons: function() {
                document.querySelectorAll('[data-action]').forEach(button => {
                    button.addEventListener('click', (e) => {
                        const action = button.dataset.action;

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
                                if (button.hasAttribute('data-reload')) {
                                    this.reloadPage(button.dataset.message);
                                }
                        }
                    });
                });
            },

            // Set up confirmation dialogs
            setupConfirmationDialogs: function() {
                document.querySelectorAll('[data-confirm]').forEach(element => {
                    element.addEventListener('click', (e) => {
                        if (!this.config.confirmationRequired) return;

                        const message = element.dataset.confirm || 'Are you sure?';

                        if (!confirm(message)) {
                            e.preventDefault();
                            e.stopPropagation();
                            return false;
                        } else {
                            this.showLoading();

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

            // Create loading indicator
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
                        border-top: 4px solid var(--primary-color);
                        border-radius: 50%;
                        animation: spin 1s linear infinite;
                        margin-bottom: 10px;
                    }
                    .loading-message {
                        color: #333;
                        font-family: 'Poppins', sans-serif;
                        font-size: 1rem;
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

            // Reload page
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

        // Utility for dynamically added buttons
        window.reloadPage = function() {
            PageActions.reloadPage();
        };
    </script>
</body>
</html>
