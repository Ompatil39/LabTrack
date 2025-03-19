<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Lab Monitoring System</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.min.js"
        integrity="sha512-L0Shl7nXXzIlBSUUPpxrokqq4ojqgZFQczTYlGjzONGTDAcLremjwaWv5A+EDLnxhQzY5xUZPWLOLqYRkY0Cbw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

    <!-- Include Sparkline Plugin -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-sparklines/2.1.2/jquery.sparkline.min.js"></script>

    <link rel="stylesheet" href="../public/css/style.css" />
    <script src="https://kit.fontawesome.com/0319a73572.js" crossorigin="anonymous"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,200,0,200&icon_names=dns" />
    <link
        href="https://fonts.googleapis.com/css2?family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100..900;1,100..900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <style>
        /* Notification Styles */
        .notification-container {
            position: absolute;
            top: 60px;
            right: 20px;
            width: 350px;
            background-color: var(--card-bg-color);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            z-index: 1000;
            overflow: hidden;
            display: none;
        }

        .notification-container.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--padding);
            border-bottom: 1px solid var(--border-color);
        }

        .notification-header h3 {
            margin: 0;
            color: var(--main-text-color);
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 1rem;
        }

        .notification-header .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .notification-header button {
            background: none;
            border: none;
            color: var(--secondary-text-color);
            cursor: pointer;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .notification-header button:hover {
            background-color: var(--hover-bg-color);
            color: var(--primary-accent);
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            padding: var(--padding);
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: var(--hover-bg-color);
        }

        .notification-item.unread {
            background-color: rgba(52, 152, 219, 0.05);
        }

        .notification-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .notification-item.urgent .notification-icon {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--warning-accent);
        }

        .notification-item.warning .notification-icon {
            background-color: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .notification-item.info .notification-icon {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--primary-accent);
        }

        .notification-item.success .notification-icon {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--secondary-accent);
        }

        .notification-content {
            flex-grow: 1;
        }

        .notification-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            color: var(--main-text-color);
            margin-bottom: 3px;
            font-size: 0.9rem;
        }

        .notification-message {
            color: var(--secondary-text-color);
            font-size: 0.85rem;
            font-family: 'Roboto', sans-serif;
            margin-bottom: 3px;
        }

        .notification-time {
            color: var(--secondary-text-color);
            font-size: 0.75rem;
            font-family: 'Roboto', sans-serif;
            opacity: 0.8;
        }

        .notification-actions button {
            background: none;
            border: none;
            color: var(--secondary-text-color);
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .notification-actions button:hover {
            background-color: var(--hover-bg-color);
            color: var(--primary-accent);
        }

        .notification-actions button:hover i.fa-trash {
            color: var(--warning-accent);
        }

        .notification-footer {
            padding: 0.75rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }

        .view-all {
            background-color: var(--hover-bg-color);
            color: var(--primary-accent);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            width: 100%;
        }

        .view-all:hover {
            background-color: var(--primary-accent);
            color: white;
        }

        /* Notification Bell in Header */
        .notification-indicator {
            position: relative;
        }

        .notification-bell {
            background: none;
            border: none;
            color: var(--main-text-color);
            font-size: 1.1rem;
            cursor: pointer;
            margin-right: 15px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .notification-bell:hover {
            background-color: var(--hover-bg-color);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: var(--warning-accent);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
        }

        /* Animation for new notifications */
        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        .notification-pulse {
            animation: pulse 1s infinite;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <span><i class="fa-brands fa-watchman-monitoring colour"></i>LabTrack</span>
            </div>
            <hr class="solid" />
            <ul class="menu">
                <li class="menu-title">Menu</li>
                <!-- Dashboard Link -->
                <li>
                    <a href="index.php">
                        <i class="fa-solid fa-chart-pie"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="labs.php">
                        <i class="fa-solid fa-network-wired"></i>
                        Labs
                    </a>
                </li>
                <li>
                    <a href="addLab.php">
                        <i class="fa-solid fa-plus"></i></i><span>Add Lab</span>
                    </a>
                </li>
                <li>
                    <a href="addDevice.php">
                        <i class="fa-solid fa-plus"></i></i><span>Add Devices</span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php">
                        <i class="fa-solid fa-warehouse"></i> Inventory
                    </a>
                </li>
                <li>
                    <a href="grievance.php">
                        <i class="fa-solid fa-paper-plane"></i> Grievance
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <!-- User Info Section -->
                <div class="sub-heading">
                    <span>Edit Devices </span>
                </div>
                <div class="user-info">
                    <i class="fa-solid fa-circle-user"></i><span class="font-rale"> Administrator</span>
                </div>
            </div>

            <!-- CONTENT START of BELOW HEADER -->
            <!-- Notification Container -->
            <div class="notification-container">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <div class="notification-actions">
                        <button class="mark-all-read" title="Mark all as read">
                            <i class="fa-solid fa-check-double"></i>
                        </button>
                        <button class="notification-settings" title="Notification settings">
                            <i class="fa-solid fa-gear"></i>
                        </button>
                    </div>
                </div>

                <div class="notification-list">
                    <!-- Urgent Notification -->
                    <div class="notification-item urgent unread">
                        <div class="notification-icon">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">Temperature Alert - Lab 3</div>
                            <div class="notification-message">Temperature exceeded threshold (28.5°C)</div>
                            <div class="notification-time">2 minutes ago</div>
                        </div>
                        <div class="notification-actions">
                            <button class="notification-action" title="Mark as read">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Warning Notification -->
                    <div class="notification-item warning unread">
                        <div class="notification-icon">
                            <i class="fa-solid fa-exclamation"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">Low Disk Space - Server 02</div>
                            <div class="notification-message">Storage capacity at 85%</div>
                            <div class="notification-time">25 minutes ago</div>
                        </div>
                        <div class="notification-actions">
                            <button class="notification-action" title="Mark as read">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Info Notification -->
                    <div class="notification-item info">
                        <div class="notification-icon">
                            <i class="fa-solid fa-info"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">Maintenance Complete</div>
                            <div class="notification-message">Weekly maintenance on Lab 1 completed</div>
                            <div class="notification-time">2 hours ago</div>
                        </div>
                        <div class="notification-actions">
                            <button class="notification-action" title="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Success Notification -->
                    <div class="notification-item success">
                        <div class="notification-icon">
                            <i class="fa-solid fa-check-circle"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">New Device Added</div>
                            <div class="notification-message">Workstation WS-042 added to Lab 2</div>
                            <div class="notification-time">Yesterday</div>
                        </div>
                        <div class="notification-actions">
                            <button class="notification-action" title="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="notification-footer">
                    <button class="view-all">View All Notifications</button>
                </div>
            </div>

            <!-- Notification Indicator for Header -->
            <div class="notification-indicator">
                <button class="notification-bell">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notification-badge">2</span>
                </button>
            </div>
            <!-- CONTENT END of BELOW HEADER  -->
        </div>
        <!-- CONTENT END  -->
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle notification panel when bell is clicked
            const notificationBell = document.querySelector('.notification-bell');
            const notificationContainer = document.querySelector('.notification-container');

            if (notificationBell && notificationContainer) {
                notificationBell.addEventListener('click', function() {
                    notificationContainer.classList.toggle('show');

                    // Remove pulse animation when opened
                    notificationBell.classList.remove('notification-pulse');

                    // Update badge count when opened (this would be dynamic in a real app)
                    setTimeout(() => {
                        const badge = notificationBell.querySelector('.notification-badge');
                        if (badge) {
                            badge.textContent = '0';
                            badge.style.display = 'none';
                        }
                    }, 2000);
                });

                // Close notifications when clicking outside
                document.addEventListener('click', function(event) {
                    if (!notificationContainer.contains(event.target) &&
                        !notificationBell.contains(event.target) &&
                        notificationContainer.classList.contains('show')) {
                        notificationContainer.classList.remove('show');
                    }
                });

                // Mark as read functionality
                const markReadButtons = document.querySelectorAll('.notification-action .fa-check');
                markReadButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const notificationItem = this.closest('.notification-item');
                        notificationItem.classList.remove('unread');

                        // Update unread count
                        updateUnreadCount();
                    });
                });

                // Mark all as read
                const markAllReadButton = document.querySelector('.mark-all-read');
                if (markAllReadButton) {
                    markAllReadButton.addEventListener('click', function() {
                        const unreadItems = document.querySelectorAll('.notification-item.unread');
                        unreadItems.forEach(item => {
                            item.classList.remove('unread');
                        });

                        // Update unread count
                        updateUnreadCount();
                    });
                }

                // Delete notification
                const deleteButtons = document.querySelectorAll('.notification-action .fa-trash');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const notificationItem = this.closest('.notification-item');

                        // Animate removal
                        notificationItem.style.height = notificationItem.offsetHeight + 'px';
                        notificationItem.style.opacity = '1';

                        setTimeout(() => {
                            notificationItem.style.height = '0';
                            notificationItem.style.opacity = '0';
                            notificationItem.style.padding = '0';
                            notificationItem.style.margin = '0';
                            notificationItem.style.overflow = 'hidden';
                        }, 10);

                        setTimeout(() => {
                            notificationItem.remove();
                        }, 300);
                    });
                });

                // Function to update the unread count badge
                function updateUnreadCount() {
                    const unreadItems = document.querySelectorAll('.notification-item.unread');
                    const badge = document.querySelector('.notification-badge');

                    if (badge) {
                        if (unreadItems.length > 0) {
                            badge.textContent = unreadItems.length;
                            badge.style.display = 'flex';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }

                // Demo function: Add a new notification (for demonstration purposes)
                window.addNewNotification = function(type, title, message) {
                    const notificationList = document.querySelector('.notification-list');

                    if (notificationList) {
                        // Create new notification element
                        const newNotification = document.createElement('div');
                        newNotification.className = `notification-item ${type} unread`;

                        let iconClass = 'fa-info';
                        if (type === 'urgent') iconClass = 'fa-triangle-exclamation';
                        else if (type === 'warning') iconClass = 'fa-exclamation';
                        else if (type === 'success') iconClass = 'fa-check-circle';

                        newNotification.innerHTML = `
            <div class="notification-icon">
              <i class="fa-solid ${iconClass}"></i>
            </div>
            <div class="notification-content">
              <div class="notification-title">${title}</div>
              <div class="notification-message">${message}</div>
              <div class="notification-time">Just now</div>
            </div>
            <div class="notification-actions">
              <button class="notification-action" title="Mark as read">
                <i class="fa-solid fa-check"></i>
              </button>
            </div>
          `;

                        // Add it to the beginning of the list
                        notificationList.prepend(newNotification);

                        // Update badge count
                        updateUnreadCount();

                        // Add pulse animation to bell
                        notificationBell.classList.add('notification-pulse');

                        // Add event listener to the new mark as read button
                        const newMarkReadButton = newNotification.querySelector('.notification-action .fa-check');
                        newMarkReadButton.addEventListener('click', function(e) {
                            e.stopPropagation();
                            newNotification.classList.remove('unread');
                            updateUnreadCount();
                        });
                    }
                }
            }
        });
    </script>
</body>

</html>