@extends('layouts.app')

@section('title', 'Shopify Integration Dashboard')

@push('head')
<style>
/* Modern Shopify Dashboard Styles - Overriding built-in styles */
.shopify-dashboard {
    background: #f8fafc;
    min-height: 100vh;
    padding: 2rem 0;
}

.shopify-dashboard .container-fluid {
    max-width: 1400px;
    margin: 0 auto;
}

/* Modern Card Styling */
.shopify-dashboard .card {
    background: #ffffff;
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
    margin-bottom: 2rem;
    border: 1px solid #e5e7eb;
}

.shopify-dashboard .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.shopify-dashboard .card-header {
    background: #10b981;
    color: white;
    border-radius: 16px 16px 0 0 !important;
    padding: 1.5rem 2rem;
    border: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.shopify-dashboard .card-title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.shopify-dashboard .card-title i {
    font-size: 1.25rem;
    opacity: 0.9;
}

.shopify-dashboard .card-body {
    padding: 2rem;
}

/* Modern Alert Styling */
.shopify-dashboard .alert {
    border: none;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    font-weight: 500;
}

.shopify-dashboard .alert-success {
    background: #10b981;
    color: white;
    box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
}

.shopify-dashboard .alert-warning {
    background: #f59e0b;
    color: white;
    box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.2);
}

/* Modern Statistics Cards */
.shopify-dashboard .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.shopify-dashboard .stat-card {
    background: #ffffff;
    color: #1f2937;
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
}

.shopify-dashboard .stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.shopify-dashboard .stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #10b981;
    transition: all 0.3s ease;
}

.shopify-dashboard .stat-card:hover::before {
    width: 8px;
}

.shopify-dashboard .stat-card.stat-success {
    border-left: 4px solid #10b981;
}

.shopify-dashboard .stat-card.stat-warning {
    border-left: 4px solid #f59e0b;
}

.shopify-dashboard .stat-card.stat-danger {
    border-left: 4px solid #ef4444;
}

.shopify-dashboard .stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: block;
    color: #1f2937;
}

.shopify-dashboard .stat-label {
    font-size: 1rem;
    color: #6b7280;
    font-weight: 500;
}

.shopify-dashboard .stat-icon {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 2rem;
    opacity: 0.1;
    color: #10b981;
}

/* Modern Button Styling */
.shopify-dashboard .btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    transition: all 0.3s ease;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.shopify-dashboard .btn-primary {
    background: #10b981;
    color: white;
    box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
}

.shopify-dashboard .btn-primary:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 6px 8px -1px rgba(16, 185, 129, 0.3);
    color: white;
}

.shopify-dashboard .btn-warning {
    background: #f59e0b;
    color: white;
    box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.2);
}

.shopify-dashboard .btn-warning:hover {
    background: #d97706;
    transform: translateY(-1px);
    box-shadow: 0 6px 8px -1px rgba(245, 158, 11, 0.3);
    color: white;
}

.shopify-dashboard .btn-info {
    background: #06b6d4;
    color: white;
    box-shadow: 0 4px 6px -1px rgba(6, 182, 212, 0.2);
}

.shopify-dashboard .btn-info:hover {
    background: #0891b2;
    transform: translateY(-1px);
    box-shadow: 0 6px 8px -1px rgba(6, 182, 212, 0.3);
    color: white;
}

/* Modern Table Styling */
.shopify-dashboard .table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
}

.shopify-dashboard .table thead th {
    background: #10b981;
    color: white;
    border: none;
    padding: 1.25rem 1rem;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.shopify-dashboard .table tbody td {
    padding: 1.25rem 1rem;
    border: none;
    border-bottom: 1px solid #f3f4f6;
    background: white;
    color: #374151;
    font-weight: 500;
}

.shopify-dashboard .table tbody tr:hover {
    background: #f9fafb;
    transition: all 0.2s ease;
}

/* Modern Badge Styling */
.shopify-dashboard .badge {
    border-radius: 12px;
    padding: 0.5rem 1rem;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.shopify-dashboard .badge-primary {
    background: #10b981;
    color: white;
}

.shopify-dashboard .badge-success {
    background: #10b981;
    color: white;
}

.shopify-dashboard .badge-danger {
    background: #ef4444;
    color: white;
}

.shopify-dashboard .badge-warning {
    background: #f59e0b;
    color: white;
}

.shopify-dashboard .badge-info {
    background: #06b6d4;
    color: white;
}

/* Responsive Design */
@media (max-width: 768px) {
    .shopify-dashboard {
        padding: 1rem 0;
    }
    
    .shopify-dashboard .card-body {
        padding: 1.5rem;
    }
    
    .shopify-dashboard .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .shopify-dashboard .stat-number {
        font-size: 2rem;
    }
    
    .shopify-dashboard .card-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
}

/* Loading Animation */
.shopify-dashboard .loading {
    opacity: 0.7;
    pointer-events: none;
}

.shopify-dashboard .spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Custom Scrollbar */
.shopify-dashboard ::-webkit-scrollbar {
    width: 8px;
}

.shopify-dashboard ::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.shopify-dashboard ::-webkit-scrollbar-thumb {
    background: #10b981;
    border-radius: 10px;
}

.shopify-dashboard ::-webkit-scrollbar-thumb:hover {
    background: #059669;
}
</style>
@endpush

@section('content')
<div class="shopify-dashboard">
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title text-white">
                        <i class="fas fa-shopping-cart"></i>
                        Shopify Dashboard
                    </h3>
                    <div class="card-tools">
                            <a href="{{ route('vendor.integration.shopify.settings') }}" class="btn btn-primary">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if($integration && $integration->isActive())
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>Connected!</strong> Your Shopify store is connected and active.
                            <br>
                            <small>Shop Domain: {{ $integration->shop_domain }}</small>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Not Connected!</strong> Please connect your Shopify store to start receiving order notifications.
                            <br>
                                <a href="{{ route('vendor.integration.shopify.settings') }}" class="btn btn-primary mt-3">
                                Connect Shopify
                            </a>
                        </div>
                    @endif

                    @if($integration && $integration->isActive())
                            <!-- Modern Statistics Cards -->
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-shopping-bag"></i>
                                    </div>
                                    <span class="stat-number">{{ $orderStatistics['total_orders'] ?? 0 }}</span>
                                    <div class="stat-label">Total Orders</div>
                                </div>
                                <div class="stat-card stat-success">
                                    <div class="stat-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <span class="stat-number">{{ $orderStatistics['paid_orders'] ?? 0 }}</span>
                                    <div class="stat-label">Paid Orders</div>
                                </div>
                                <div class="stat-card stat-warning">
                                    <div class="stat-icon">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <span class="stat-number">{{ $orderStatistics['fulfilled_orders'] ?? 0 }}</span>
                                    <div class="stat-label">Fulfilled Orders</div>
                                </div>
                                <div class="stat-card stat-danger">
                                    <div class="stat-icon">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                    <span class="stat-number">{{ $notificationStatistics['total_notifications'] ?? 0 }}</span>
                                    <div class="stat-label">Notifications Sent</div>
                            </div>
                        </div>

                        <!-- Recent Orders -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                            <h3 class="card-title text-white">
                                                <i class="fas fa-list"></i>
                                                Recent Orders
                                            </h3>
                                        <div class="card-tools">
                                                <a href="{{ route('vendor.integration.shopify.orders') }}" class="btn btn-primary">
                                                View All Orders
                                            </a>
                                                <a href="{{ route('vendor.integration.shopify.test_notifications') }}" class="btn btn-warning">
                                                <i class="fas fa-vial"></i> Test Notifications
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-hover text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Customer</th>
                                                    <th>Status</th>
                                                    <th>Total</th>
                                                    <th>Date</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($recentOrders as $order)
                                                    <tr>
                                                            <td><strong>{{ $order->order_number }}</strong></td>
                                                        <td>
                                                            @if($order->name)
                                                                    <div>{{ $order->name }}</div>
                                                                @if($order->email)
                                                                        <small class="text-muted">{{ $order->email }}</small>
                                                                    @endif
                                                                @else
                                                                    <span class="text-muted">N/A</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-{{ $order->status === 'open' ? 'primary' : ($order->status === 'closed' ? 'success' : 'danger') }}">
                                                                {{ $order->status_label }}
                                                            </span>
                                                        </td>
                                                            <td><strong>{{ $order->formatted_total_price }}</strong></td>
                                                        <td>{{ $order->created_at->format('M d, Y H:i') }}</td>
                                                        <td>
                                                                <a href="{{ route('vendor.integration.shopify.order_details', $order->shopify_order_id) }}" class="btn btn-info">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                            <td colspan="6" class="text-center py-4">
                                                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                                <div class="text-muted">No orders found</div>
                                                            </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Notifications -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                            <h3 class="card-title text-white">
                                                <i class="fas fa-bell "></i>
                                                Recent Notifications
                                            </h3>
                                        <div class="card-tools">
                                                <a href="{{ route('vendor.integration.shopify.notifications') }}" class="btn btn-primary">
                                                View All Notifications
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body table-responsive p-0">
                                        <table class="table table-hover text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Order #</th>
                                                    <th>Status</th>
                                                    <th>Sent At</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($recentNotifications as $notification)
                                                    <tr>
                                                        <td>
                                                            <span class="badge badge-info">
                                                                {{ $notification->notification_type_label }}
                                                            </span>
                                                        </td>
                                                            <td><strong>{{ $notification->order->order_number ?? 'N/A' }}</strong></td>
                                                        <td>
                                                            <span class="badge badge-{{ $notification->status === 'sent' ? 'success' : ($notification->status === 'failed' ? 'danger' : 'warning') }}">
                                                                {{ $notification->status_label }}
                                                            </span>
                                                        </td>
                                                        <td>{{ $notification->sent_at ? $notification->sent_at->format('M d, Y H:i') : 'N/A' }}</td>
                                                        <td>
                                                            @if($notification->isFailed())
                                                                    <button class="btn btn-warning resend-notification" data-notification-id="{{ $notification->_id }}">
                                                                    <i class="fas fa-redo"></i> Resend
                                                                </button>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                            <td colspan="5" class="text-center py-4">
                                                                <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                                                                <div class="text-muted">No notifications found</div>
                                                            </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Handle resend notification with modern loading state
    $('.resend-notification').click(function() {
        const notificationId = $(this).data('notification-id');
        const button = $(this);
        
        button.addClass('loading').prop('disabled', true);
        button.html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        
        $.ajax({
            url: `/vendor-console/integration/shopify/notifications/${notificationId}/resend`,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // Show success message with modern styling
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Notification resent successfully',
                        confirmButtonColor: '#10b981',
                        background: '#f8fafc',
                        customClass: {
                            popup: 'rounded-lg shadow-lg',
                            title: 'text-lg font-semibold'
                        }
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.message || 'Failed to resend notification',
                        confirmButtonColor: '#ef4444',
                        background: '#f8fafc',
                        customClass: {
                            popup: 'rounded-lg shadow-lg',
                            title: 'text-lg font-semibold'
                        }
                    });
                }
            },
            error: function(xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to resend notification',
                    confirmButtonColor: '#ef4444',
                    background: '#f8fafc',
                    customClass: {
                        popup: 'rounded-lg shadow-lg',
                        title: 'text-lg font-semibold'
                    }
                });
            },
            complete: function() {
                button.removeClass('loading').prop('disabled', false);
                button.html('<i class="fas fa-redo"></i> Resend');
            }
        });
    });

    // Add smooth animations to cards
    $('.shopify-dashboard .card').each(function(index) {
        $(this).css({
            'animation-delay': (index * 0.1) + 's',
            'animation': 'fadeInUp 0.6s ease forwards'
        });
    });

    // Add hover effects to table rows
    $('.shopify-dashboard .table tbody tr').hover(
        function() {
            $(this).addClass('table-hover');
        },
        function() {
            $(this).removeClass('table-hover');
        }
    );
});

// Add CSS animation for fadeInUp
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(style);
</script>
@endpush
@endsection 