@extends('layouts.app')

@section('title', 'Shopify Orders')

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

.shopify-dashboard .btn-secondary {
    background: #6b7280;
    color: white;
    box-shadow: 0 4px 6px -1px rgba(107, 114, 128, 0.2);
}

.shopify-dashboard .btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
    box-shadow: 0 6px 8px -1px rgba(107, 114, 128, 0.3);
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

/* Modern Form Styling */
.shopify-dashboard .form-control {
    border-radius: 8px;
    border: 1px solid #d1d5db;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.shopify-dashboard .form-control:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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

.shopify-dashboard .badge-success {
    background: #10b981;
    color: white;
}

.shopify-dashboard .badge-secondary {
    background: #6b7280;
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
                        Shopify Orders
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('vendor.integration.shopify.dashboard') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select class="form-control" id="status-filter">
                                <option value="">All Statuses</option>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="financial-status-filter">
                                <option value="">All Payment Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="partially_paid">Partially Paid</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="fulfillment-status-filter">
                                <option value="">All Fulfillment Statuses</option>
                                <option value="unfulfilled">Unfulfilled</option>
                                <option value="fulfilled">Fulfilled</option>
                                <option value="partial">Partially Fulfilled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="search-filter" placeholder="Search orders...">
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="table-responsive">
                        <table class="table table-hover text-nowrap" id="orders-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Fulfillment</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $order)
                                <tr>
                                    <td>
                                        <strong>{{ $order->order_number }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $order->shopify_order_id }}</small>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $order->name }}</strong>
                                            @if($order->email)
                                                <br>
                                                <small class="text-muted">{{ $order->email }}</small>
                                            @endif
                                            @if($order->phone)
                                                <br>
                                                <small class="text-muted">{{ $order->phone }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <strong>{{ $order->formatted_total_price }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $order->currency }}</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $order->status === 'open' ? 'success' : ($order->status === 'closed' ? 'secondary' : 'danger') }}">
                                            {{ ucfirst($order->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $order->financial_status === 'paid' ? 'success' : ($order->financial_status === 'pending' ? 'warning' : 'info') }}">
                                            {{ ucfirst(str_replace('_', ' ', $order->financial_status)) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $order->fulfillment_status === 'fulfilled' ? 'success' : ($order->fulfillment_status === 'unfulfilled' ? 'warning' : 'info') }}">
                                            {{ ucfirst(str_replace('_', ' ', $order->fulfillment_status)) }}
                                        </span>
                                    </td>
                                    <td>{{ $order->created_at->format('M d, Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('vendor.integration.shopify.order_details', $order->shopify_order_id) }}" class="btn btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                        <div class="text-muted">No orders found</div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($orders instanceof \Illuminate\Pagination\LengthAwarePaginator && $orders->hasPages())
                        <div class="d-flex justify-content-center mt-4">
                            {{ $orders->links() }}
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
    // Filter functionality
    $('#status-filter, #financial-status-filter, #fulfillment-status-filter').change(function() {
        filterOrders();
    });

    $('#search-filter').on('keyup', function() {
        filterOrders();
    });

    function filterOrders() {
        const status = $('#status-filter').val();
        const financialStatus = $('#financial-status-filter').val();
        const fulfillmentStatus = $('#fulfillment-status-filter').val();
        const search = $('#search-filter').val().toLowerCase();

        $('#orders-table tbody tr').each(function() {
            let show = true;
            const row = $(this);

            // Status filter
            if (status && row.find('td:nth-child(4) .badge').text().toLowerCase() !== status.toLowerCase()) {
                show = false;
            }

            // Financial status filter
            if (financialStatus && row.find('td:nth-child(5) .badge').text().toLowerCase().replace(/\s+/g, '_') !== financialStatus.toLowerCase()) {
                show = false;
            }

            // Fulfillment status filter
            if (fulfillmentStatus && row.find('td:nth-child(6) .badge').text().toLowerCase().replace(/\s+/g, '_') !== fulfillmentStatus.toLowerCase()) {
                show = false;
            }

            // Search filter
            if (search) {
                const text = row.text().toLowerCase();
                if (!text.includes(search)) {
                    show = false;
                }
            }

            row.toggle(show);
        });
    }

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