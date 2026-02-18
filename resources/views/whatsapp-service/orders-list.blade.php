@extends('layouts.app', ['title' => __tr('WhatsApp Orders')])
@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
@endpush
@section('content')
<div class="lw-page-content py-5">
    <!-- Header Section -->
    <div class="lw-page-header">
        <div class="container-fluid mt-5">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-2">
                <h1 class="page-title mb-0" style="color: #22A755;">
                    <i class="fas fa-shopping-cart me-2"></i>{{ __tr(' WhatsApp Orders') }}
                </h1>
                <div class="d-flex">
                    <button type="button"
                        class="lw-btn btn btn-modern btn-modern-secondary btn-rounded animate__animated animate__fadeIn me-2"
                        onclick="refreshOrders()">
                        <i class="fas fa-sync-alt me-2"></i>{{ __tr(' Refresh') }}
                    </button>
                    <button type="button"
                        class="lw-btn btn btn-modern btn-modern-secondary btn-rounded animate__animated animate__fadeIn me-2"
                        onclick="testLoadOrders()">
                        <i class="fas fa-bug me-2"></i>{{ __tr(' Test Load') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Page content -->
    <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded p-4 animated-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <!-- Text -->
                        <div>
                            <h3 class="mb-1 text-dark" style="font-weight: 600;">{{ __tr('Total Orders') }}</h3>
                            <h2 class="mb-1 text-dark" style="font-weight: 800;">{{ number_format($orderStatistics['total_orders'] ?? 0) }}</h2>
                        </div>

                        <!-- Icon -->
                        <div class="rounded d-flex align-items-center justify-content-center" style="background-color: #DCFCE7; padding: 14px;">
                            <i class="fa fa-shopping-cart" style="color: #22A755; font-size: 24px;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded p-4 animated-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <!-- Text Section -->
                        <div>
                            <h3 class="mb-1 text-dark" style="font-weight: 600;">{{ __tr('Pending Orders') }}</h3>
                            <h2 class="mb-1 text-dark" style="font-weight: 800;">{{ $orderStatistics['pending_orders'] ?? 0 }}</h2>
                        </div>
                        <!-- Icon Section -->
                        <div class="rounded d-flex align-items-center justify-content-center" style="background-color: #fff8cc; padding: 14px;">
                            <i class="fas fa-clock" style="color: #e6b200; font-size: 24px;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <!-- Text Section -->
                        <div>
                            <h3 class="mb-1" style="color: #555; font-weight: 600;">{{ __tr('Completed Orders') }}</h3>
                            <h2 class="mb-1" style="color: #111; font-weight: bold;">
                                {{ number_format($orderStatistics['completed_orders'] ?? 0) }}
                            </h2>
                        </div>

                        <!-- Icon Section -->
                        <div class="rounded d-flex align-items-center justify-content-center"
                            style="background-color: #e6f8ee; padding: 16px;">
                            <i class="fa fa-check-circle" style="color: #22A755; font-size: 24px;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <!-- Text Section -->
                        <div>
                            <h3 class="mb-1" style="color: #555; font-weight: 600;">{{ __tr('Total Revenue') }}</h3>
                            <h2 class="mb-1" style="color: #111; font-weight: bold;">
                                ₹{{ number_format($orderStatistics['total_revenue'] ?? 0) }}
                            </h2>
                        </div>

                        <!-- Icon Section -->
                        <div class="rounded d-flex align-items-center justify-content-center"
                            style="background-color: #e6f0fd; padding: 16px;">
                            <i class="fas fa-rupee-sign" style="color: #2b6cb0; font-size: 24px;"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Revenue Statistics -->
        <div class="row mb-4">
            <div class="col-lg-6 col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <!-- Text Section -->
                        <div>
                            <h3 class="mb-1" style="color: #555; font-weight: 600;">{{ __tr('Total Revenue') }}</h3>
                            <h2 class="mb-1" style="color: #111; font-weight: bold;">
                                ₹{{ formatAmount($orderStatistics['total_revenue'] ?? 0) }}
                            </h2>
                        </div>

                        <!-- Icon Section -->
                        <div class="rounded d-flex align-items-center justify-content-center"
                            style="background-color: #e6f0fd; padding: 16px;">
                            <i class="fas fa-rupee-sign" style="color: #2b6cb0; font-size: 24px;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6 mb-4">
                <div class="card border-0 shadow-sm rounded p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <!-- Text Section -->
                        <div>
                            <h3 class="mb-1" style="color: #555; font-weight: 600;">{{ __tr('Pending Revenue') }}</h3>
                            <h2 class="mb-1" style="color: #111; font-weight: bold;">
                                {{ formatAmount($orderStatistics['pending_revenue'] ?? 0) }}
                            </h2>
                        </div>

                        <!-- Icon Section -->
                        <div class="rounded d-flex align-items-center justify-content-center"
                            style="background-color: #fef8e7; padding: 16px;">
                            <i class="fa fa-check-circle" style="color: #f1b600; font-size: 24px;"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form id="ordersFilterForm">
                    <div class="row g-3 align-items-end">
                        <!-- Status -->
                        <div class="col-md-3">
                            <label class="mb-1 text-dark" style="font-weight: 600;">{{ __tr('Status') }}</label>
                            <select name="status" class="form-control">
                                <option value="">{{ __tr('All Statuses') }}</option>
                                <option value="pending">{{ __tr('Pending') }}</option>
                                <option value="awaiting_address">{{ __tr('Awaiting Address') }}</option>
                                <option value="awaiting_payment">{{ __tr('Awaiting Payment') }}</option>
                                <option value="paid">{{ __tr('Paid') }}</option>
                                <option value="confirmed">{{ __tr('Confirmed') }}</option>
                                <option value="shipped">{{ __tr('Shipped') }}</option>
                                <option value="delivered">{{ __tr('Delivered') }}</option>
                                <option value="cancelled">{{ __tr('Cancelled') }}</option>
                            </select>
                        </div>

                        <!-- Order ID -->
                        <div class="col-md-3">
                            <label class="mb-1 text-dark" style="font-weight: 600;">{{ __tr('Order ID') }}</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="fa fa-search text-muted"></i>
                                </span>
                                <input 
                                    type="text" 
                                    name="order_id" 
                                    class="form-control border-start-0" 
                                    placeholder="{{ __tr('Search by Order ID') }}"
                                    oninput="this.style.boxShadow = this.value ? '0 0 0 0.2rem rgba(20, 83, 45, 0.6)' : 'none'"
                                >
                            </div>
                        </div>


                        <!-- Customer Phone -->
                        <div class="col-md-3">
                            <label class="mb-1 text-dark" style="font-weight: 600;">{{ __tr('Customer Phone') }}</label>
                            <input 
                                type="text" 
                                name="customer_phone" 
                                class="form-control" 
                                placeholder="{{ __tr('Enter phone number') }}"
                                oninput="this.style.boxShadow = this.value ? '0 0 0 0.2rem rgba(20, 83, 45, 0.6)' : 'none'"
                            >
                        </div>

                        <!-- Buttons -->
                        <div class="col-md-3 text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" 
                                    onclick="clearFilters()" 
                                    style="
                                        margin-right: 8px;
                                        border: 1px solid #6c757d;
                                        color: #6c757d;
                                        border-radius: 8px;
                                        padding: 6px 16px;
                                        background-color: transparent;
                                        transition: all 0.3s ease;
                                        position: relative;
                                        overflow: hidden;
                                    " 
                                    onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.backgroundColor='#f8f9fa'; this.style.color='#212529'; this.style.borderColor='#5c636a'"
                                    onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none'; this.style.backgroundColor='transparent'; this.style.color='#6c757d'; this.style.borderColor='#6c757d'"
                                    onmousedown="this.style.setProperty('--ripple', 'active')"
                                >
                                    <i class="far fa-times-circle text-danger"></i> {{ __tr('Clear') }}
                                </button>
                               <button type="submit" 
                                    class="btn" 
                                    style="
                                        background-color: #15803D;
                                        color: #fff;
                                        border-radius: 8px;
                                        padding: 10px 16px;
                                        border: 1px solid #14532d;
                                        transition: all 0.3s ease;
                                        transform: scale(1);
                                    " 
                                    onmouseover="this.style.backgroundColor='#166534'; this.style.transform='scale(1.05)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'"
                                    onmouseout="this.style.backgroundColor='#15803D'; this.style.transform='scale(1)'; this.style.boxShadow='none'"
                                >
                                    <i class="fa fa-filter me-1"></i> {{ __tr('Apply Filters') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>




        <!-- Modern Table Container -->
        <div class="modern-table-container">
            <!-- Table Header with Controls -->
            <div class="table-header-controls">
                <div class="table-title-section">
                    <h2 class="table-title">{{ __tr('Orders List') }}</h2>
                </div>
                <div class="table-controls">
                    <div class="entries-control">
                        <label for="entries-per-page">{{ __tr('Show') }}</label>
                        <select id="entries-per-page" class="entries-select">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100" selected>100</option>
                        </select>
                        <span class="entries-text">{{ __tr('entries per page') }}</span>
                    </div>
                </div>
            </div>

            <!-- Modern DataTable -->
            <div class="table-responsive">
                <table class="table modern-datatable" id="ordersTable">
                    <thead>
                        <tr>
                            <th>{{ __tr('Order ID') }}</th>
                            <th>{{ __tr('Customer') }}</th>
                                                            <th>{{ __tr('Order Items') }}</th>
                            <th>{{ __tr('Amount') }}</th>
                            <th>{{ __tr('Status') }}</th>
                            <th>{{ __tr('Date') }}</th>
                            <th>{{ __tr('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <!-- Orders will be loaded here via AJAX -->
                    </tbody>
                </table>
            </div>

            <!-- Table Footer with Info -->
            <div class="table-footer">
                <div class="table-info">
                    <span id="table-info-text">{{ __tr('Showing 0 to 0 of 0 entries') }}</span>
                </div>
                <div id="ordersPagination" class="pagination-container">
                    <!-- Pagination will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Order Status Update Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __tr('Update Order Status') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="updateStatusForm">
                <div class="modal-body">
                    <input type="hidden" id="orderUidForUpdate" name="order_uid">
                    <div class="form-group">
                        <label>{{ __tr('New Status') }}</label>
                        <select name="status" class="form-control" required>
                            <option value="pending">{{ __tr('Pending') }}</option>
                            <option value="awaiting_address">{{ __tr('Awaiting Address') }}</option>
                            <option value="awaiting_payment">{{ __tr('Awaiting Payment') }}</option>
                            <option value="paid">{{ __tr('Paid') }}</option>
                            <option value="confirmed">{{ __tr('Confirmed') }}</option>
                            <option value="shipped">{{ __tr('Shipped') }}</option>
                            <option value="delivered">{{ __tr('Delivered') }}</option>
                            <option value="cancelled">{{ __tr('Cancelled') }}</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __tr('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __tr('Update Status') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    /* Modern Table Container Styles */
    .modern-table-container {
        background: #ffffff !important;
        border-radius: 12px !important;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
        overflow: hidden !important;
        margin-bottom: 2rem !important;
        border: 1px solid #e9ecef !important;
    }

    /* Table Header Controls */
    .table-header-controls {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 1.5rem 2rem !important;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        border-bottom: 1px solid #dee2e6 !important;
    }

    .table-title-section {
        display: flex !important;
        align-items: center !important;
    }

    .table-title {
        font-size: 1.25rem !important;
        font-weight: 600 !important;
        color: #495057 !important;
        margin: 0 !important;
    }

    .table-controls {
        display: flex !important;
        align-items: center !important;
        gap: 1.5rem !important;
    }

    .entries-control {
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
    }

    .entries-control label {
        font-size: 0.875rem !important;
        color: #6c757d !important;
        margin: 0 !important;
    }

    .entries-select {
        padding: 0.375rem 0.75rem !important;
        border: 1px solid #ced4da !important;
        border-radius: 6px !important;
        background: #ffffff !important;
        font-size: 0.875rem !important;
        color: #495057 !important;
        min-width: 60px !important;
    }

    .entries-text {
        font-size: 0.875rem !important;
        color: #6c757d !important;
    }

    .search-control {
        position: relative !important;
    }

    .search-input {
        padding: 0.5rem 1rem 0.5rem 2.5rem !important;
        border: 1px solid #ced4da !important;
        border-radius: 8px !important;
        background: #ffffff !important;
        font-size: 0.875rem !important;
        color: #495057 !important;
        min-width: 200px !important;
        transition: all 0.3s ease !important;
    }

    .search-input:focus {
        outline: none !important;
        border-color: #007bff !important;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1) !important;
    }

    .search-icon {
        position: absolute !important;
        left: 0.75rem !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
    }

    .items-summary {
        max-width: 200px !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        font-size: 0.9rem !important;
        color: #666 !important;
    }
        color: #6c757d !important;
        font-size: 0.875rem !important;
    }

    /* Modern DataTable Styles */
    .modern-datatable {
        width: 100% !important;
        border-collapse: collapse !important;
    }

    .modern-datatable thead th {
        background: #f8f9fa !important;
        border: none !important;
        padding: 1rem 1.5rem !important;
        font-weight: 600 !important;
        font-size: 0.875rem !important;
        color: #495057 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        position: relative !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
    }

    .modern-datatable thead th:hover {
        background: #e9ecef !important;
    }

    .modern-datatable tbody tr {
        border-bottom: 1px solid #f1f3f4 !important;
        transition: all 0.3s ease !important;
    }

    .modern-datatable tbody tr:hover {
        background: #f8f9fa !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    }

    .modern-datatable tbody td {
        padding: 1rem 1.5rem !important;
        font-size: 0.875rem !important;
        color: #495057 !important;
        vertical-align: middle !important;
    }

    /* Status Badge Styles */
    .status-badge {
        display: inline-flex !important;
        align-items: center !important;
        padding: 0.5rem 1rem !important;
        border-radius: 50px !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        letter-spacing: 0.5px !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08) !important;
        transition: all 0.3s ease !important;
        text-transform: uppercase !important;
    }

    .status-badge:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12) !important;
    }

    .status-icon {
        margin-right: 0.5rem !important;
        font-size: 0.875rem !important;
    }

    .status-approved {
        background: linear-gradient(135deg, #d4edda, #c3e6cb) !important;
        color: #155724 !important;
        border-left: 3px solid #28a745 !important;
    }

    .status-rejected {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb) !important;
        color: #721c24 !important;
        border-left: 3px solid #dc3545 !important;
    }

    .status-pending {
        background: linear-gradient(135deg, #fff3cd, #ffeaa7) !important;
        color: #856404 !important;
        border-left: 3px solid #ffc107 !important;
    }

    .status-other {
        background: linear-gradient(135deg, #e9ecef, #dee2e6) !important;
        color: #495057 !important;
        border-left: 3px solid #adb5bd !important;
    }

    /* Action Buttons Container */
    .action-buttons-container {
        display: flex !important;
        align-items: center !important;
        gap: 0.4rem !important;
        justify-content: flex-start !important;
        flex-wrap: nowrap !important;
        white-space: nowrap !important;
        min-width: fit-content !important;
        overflow: visible !important;
    }

    .action-btn {
        width: 34px !important;
        height: 34px !important;
        border: none !important;
        border-radius: 6px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.8rem !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
        position: relative !important;
        overflow: hidden !important;
        text-decoration: none !important;
        color: white !important;
        box-shadow: 0 2px 4px rgba(11, 119, 83, 0.2) !important;
        flex-shrink: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .action-btn:hover,
    .action-btn:focus {
        transform: translateY(-1px) !important;
        text-decoration: none !important;
        color: white !important;
        outline: none !important;
    }

    /* View button - Blue gradient */
    .view-btn {
        background: linear-gradient(135deg, #007bff, #0056b3) !important;
        box-shadow: 0 2px 4px rgba(0, 123, 255, 0.2) !important;
    }

    .view-btn:hover,
    .view-btn:focus {
        background: linear-gradient(135deg, #0056b3, #004085) !important;
        box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3) !important;
    }

    /* Edit button - Green gradient */
    .edit-btn {
        background: linear-gradient(135deg, #0B7753, #22A755) !important;
        box-shadow: 0 2px 4px rgba(11, 119, 83, 0.2) !important;
    }

    .edit-btn:hover,
    .edit-btn:focus {
        background: linear-gradient(135deg, #095c41, #1D9248) !important;
        box-shadow: 0 4px 8px rgba(11, 119, 83, 0.3) !important;
    }

    /* Table Footer */
    .table-footer {
        padding: 1rem 2rem !important;
        background: #f8f9fa !important;
        border-top: 1px solid #dee2e6 !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
    }

    .table-info {
        font-size: 0.875rem !important;
        color: #6c757d !important;
    }

    .pagination-container {
        display: flex !important;
        align-items: center !important;
    }

    /* Modern Button Styles */
    .btn-modern {
        transition: all 0.3s ease !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08) !important;
        font-weight: 600 !important;
        letter-spacing: 0.025em !important;
        padding: 0.5rem 1rem !important;
        margin: 0 0.2rem !important;
    }

    .btn-modern:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08) !important;
    }

    .btn-modern-secondary {
        background: linear-gradient(135deg, #6edffb, #4aa3f7) !important;
        border: none !important;
        color: white !important;
    }

    .btn-modern-green {
        background: linear-gradient(135deg, #0B7753, #22A755) !important;
        border: none !important;
        color: white !important;
    }

    .btn-modern-green:hover {
        background: linear-gradient(135deg, #22A755, #1D9248) !important;
        color: white !important;
    }

    .btn-rounded {
        border-radius: 12px !important;
    }

    /* Page Title Styling */
    .page-title {
        font-size: 1.75rem !important;
        font-weight: 600 !important;
        color: #333 !important;
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1.2 !important;
        animation: fadeInLeft 0.5s ease-out !important;
    }

    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .table-header-controls {
            flex-direction: column !important;
            gap: 1rem !important;
            align-items: stretch !important;
        }

        .table-controls {
            justify-content: space-between !important;
        }
    }

    @media (max-width: 768px) {
        .table-header-controls {
            padding: 1rem !important;
        }

        .table-controls {
            flex-direction: column !important;
            gap: 1rem !important;
        }

        .entries-control {
            justify-content: center !important;
        }

        .search-control {
            width: 100% !important;
        }

        .search-input {
            width: 100% !important;
            min-width: auto !important;
        }

        .modern-datatable thead th,
        .modern-datatable tbody td {
            padding: 0.75rem 1rem !important;
        }

        .action-buttons-container {
            flex-wrap: nowrap !important;
            gap: 0.3rem !important;
            overflow: visible !important;
            min-width: fit-content !important;
        }

        .action-btn {
            width: 30px !important;
            height: 30px !important;
            font-size: 0.7rem !important;
            flex-shrink: 0 !important;
            border-radius: 5px !important;
        }

        .page-title {
            font-size: 1.5rem !important;
        }
    }

    @media (max-width: 576px) {
        .table-title {
            font-size: 1.125rem !important;
        }

        .modern-datatable thead th,
        .modern-datatable tbody td {
            padding: 0.5rem 0.75rem !important;
            font-size: 0.8rem !important;
        }

        .status-badge {
            padding: 0.375rem 0.75rem !important;
            font-size: 0.7rem !important;
        }

        .action-btn {
            width: 26px !important;
            height: 26px !important;
            font-size: 0.65rem !important;
            flex-shrink: 0 !important;
            border-radius: 4px !important;
        }

        .btn-modern {
            padding: 0.375rem 0.75rem !important;
            font-size: 0.875rem !important;
        }
    }
</style>

@endsection

@push('appScripts')
<script>
let currentPage = 1;
let currentFilters = {};

$(document).ready(function() {
    console.log('Orders list page loaded');
    
    // Setup CSRF token for all AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Load initial data
    console.log('Loading initial data...');
    loadOrders();
    loadOrderStatistics();

    // Initialize modern table features
    initializeModernTableFeatures();

    // Filter form submission
    $('#ordersFilterForm').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        currentFilters = $(this).serialize();
        loadOrders();
    });

    // Status update form submission
    $('#updateStatusForm').on('submit', function(e) {
        e.preventDefault();
        updateOrderStatus();
    });
});

// Initialize modern table features
function initializeModernTableFeatures() {
    // Search functionality
    $('#table-search').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        filterTableRows(searchTerm);
    });

    // Entries per page functionality
    $('#entries-per-page').on('change', function() {
        const perPage = $(this).val();
        currentPage = 1;
        // Add per_page to current filters
        if (currentFilters && typeof currentFilters === 'string') {
            currentFilters += '&per_page=' + perPage;
        } else {
            currentFilters = 'per_page=' + perPage;
        }
        loadOrders();
    });

    // Initialize animations for buttons
    const buttons = document.querySelectorAll('.btn-modern');
    buttons.forEach((button, index) => {
        setTimeout(() => {
            button.classList.add('animate__animated', 'animate__fadeIn');
            button.style.opacity = '1';
        }, index * 150);
    });

    // Add hover effects to modern buttons
    $(document).on('mouseenter', '.btn-modern', function() {
        $(this).addClass('animate__animated animate__pulse');
    }).on('mouseleave', '.btn-modern', function() {
        $(this).removeClass('animate__animated animate__pulse');
    });

    // Action button hover effects
    $(document).on('mouseenter', '.action-btn', function() {
        $(this).addClass('action-btn-hover');
    }).on('mouseleave', '.action-btn', function() {
        $(this).removeClass('action-btn-hover');
    });
}

// Filter table rows based on search term
function filterTableRows(searchTerm) {
    $('#ordersTable tbody tr').each(function() {
        const rowText = $(this).text().toLowerCase();
        if (rowText.includes(searchTerm)) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

function loadOrders(page = 1) {
    currentPage = page;
    
    // Build query parameters properly
    let queryParams = 'page=' + page;
    if (currentFilters && typeof currentFilters === 'string') {
        queryParams += '&' + currentFilters;
    }
    
    console.log('Loading orders with params:', queryParams);
    
    $.ajax({
        url: "{{ route('vendor.whatsapp.orders.data') }}",
        method: 'GET',
        data: queryParams,
        beforeSend: function() {
            $('#ordersTableBody').html('<tr><td colspan="7" class="text-center">{{ __tr("Loading...") }}</td></tr>');
        },
        success: function(response) {
            console.log('Orders response:', response);
            if (response.reaction == 1) {
                renderOrdersTable(response.data.orders);
                renderPagination(response.data.orders);
            } else {
                console.error('Failed to load orders:', response);
                showErrorMessage('{{ __tr("Failed to load orders") }}');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            console.error('Response:', xhr.responseText);
            showErrorMessage('{{ __tr("Error loading orders") }}');
        }
    });
}

function renderOrdersTable(orders) {
    console.log('Rendering orders table:', orders);
    let html = '';

    if (orders.data && orders.data.length > 0) {
        console.log('Found', orders.data.length, 'orders');
        orders.data.forEach(function(order, index) {
            console.log('Processing order', index + 1, ':', order);
            
            // Debug: Log the formatted_final_amount specifically
            console.log('Order formatted_final_amount:', order.formatted_final_amount);
            console.log('Order total_amount:', order.total_amount);
            console.log('Order currency:', order.currency);
            
            const statusBadge = getModernStatusBadge(order.status, getStatusLabel(order.status));
            
            // Create a fallback for formatted_final_amount
            let displayAmount = order.formatted_final_amount;
            if (!displayAmount || displayAmount === 'undefined') {
                const finalAmount = (order.total_amount || 0) - (order.discount_amount || 0);
                displayAmount = (order.currency || 'INR') + ' ' + finalAmount.toFixed(2);
            }
            
            html += `
                <tr>
                    <td><strong>${order.order_id}</strong></td>
                    <td>
                        <div>${order.customer_name || 'N/A'}</div>
                        <small class="text-muted">${order.customer_phone}</small>
                    </td>
                    <td>
                        <div class="items-summary" title="${order.items_summary || 'No items'}">
                            ${order.items_summary || 'No items'}
                        </div>
                    </td>
                    <td><strong>${displayAmount}</strong></td>
                    <td>${statusBadge}</td>
                    <td>${formatDate(order.created_at)}</td>
                    <td>
                        <div class="action-buttons-container">
                            <button class="action-btn view-btn" onclick="viewOrder('${order._uid}')" title="{{ __tr('View Order') }}">
                                <i class="fa fa-eye"></i>
                            </button>
                            <button class="action-btn edit-btn" onclick="showUpdateStatusModal('${order._uid}', '${order.status}')" title="{{ __tr('Update Status') }}">
                                <i class="fa fa-edit"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
    } else {
        console.log('No orders found');
        html = '<tr><td colspan="7" class="text-center">{{ __tr("No orders found") }}</td></tr>';
    }

    $('#ordersTableBody').html(html);
    updateTableInfo(orders);
}

function getStatusLabel(status) {
    const statusLabels = {
        'pending': '{{ __tr("Pending") }}',
        'awaiting_address': '{{ __tr("Awaiting Address") }}',
        'awaiting_payment': '{{ __tr("Awaiting Payment") }}',
        'paid': '{{ __tr("Paid") }}',
        'confirmed': '{{ __tr("Confirmed") }}',
        'shipped': '{{ __tr("Shipped") }}',
        'delivered': '{{ __tr("Delivered") }}',
        'cancelled': '{{ __tr("Cancelled") }}',
        'refunded': '{{ __tr("Refunded") }}'
    };
    
    return statusLabels[status] || status;
}

function getModernStatusBadge(status, statusLabel) {
    const statusClasses = {
        'pending': 'status-pending',
        'awaiting_address': 'status-pending',
        'awaiting_payment': 'status-pending',
        'paid': 'status-approved',
        'confirmed': 'status-approved',
        'shipped': 'status-approved',
        'delivered': 'status-approved',
        'cancelled': 'status-rejected',
        'refunded': 'status-other'
    };

    const statusIcons = {
        'pending': 'fa-clock',
        'awaiting_address': 'fa-map-marker-alt',
        'awaiting_payment': 'fa-credit-card',
        'paid': 'fa-check-circle',
        'confirmed': 'fa-check-circle',
        'shipped': 'fa-truck',
        'delivered': 'fa-check-circle',
        'cancelled': 'fa-times-circle',
        'refunded': 'fa-undo'
    };

    const badgeClass = statusClasses[status] || 'status-other';
    const iconClass = statusIcons[status] || 'fa-info-circle';

    return `
        <div class="status-badge ${badgeClass}">
            <i class="fa ${iconClass} status-icon"></i>
            <span class="status-text">${statusLabel}</span>
        </div>
    `;
}

function updateTableInfo(orders) {
    if (orders && orders.data) {
        const start = ((orders.current_page - 1) * orders.per_page) + 1;
        const end = Math.min(orders.current_page * orders.per_page, orders.total);
        const total = orders.total;

        let infoText = `{{ __tr('Showing') }} ${start} {{ __tr('to') }} ${end} {{ __tr('of') }} ${total} {{ __tr('entries') }}`;
        $('#table-info-text').text(infoText);
    }
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString();
}

function viewOrder(orderUid) {
    window.location.href = "{{ route('vendor.whatsapp.orders.details', ':orderUid') }}".replace(':orderUid', orderUid);
}

function showUpdateStatusModal(orderUid, currentStatus) {
    $('#orderUidForUpdate').val(orderUid);
    $('#updateStatusForm select[name="status"]').val(currentStatus);
    $('#updateStatusModal').modal('show');
}

function updateOrderStatus() {
    const orderUid = $('#orderUidForUpdate').val();
    const formData = $('#updateStatusForm').serialize();
    
    $.ajax({
        url: "{{ route('vendor.whatsapp.orders.update_status', ':orderUid') }}".replace(':orderUid', orderUid),
        method: 'PATCH',
        data: formData,
        beforeSend: function() {
            // Disable submit button to prevent double submission
            $('#updateStatusForm button[type="submit"]').prop('disabled', true);
        },
        success: function(response) {
            if (response.reaction == 1) {
                $('#updateStatusModal').modal('hide');
                showSuccessMessage('{{ __tr("Order status updated successfully") }}');
                // Refresh both orders list and statistics
                loadOrders(currentPage);
                loadOrderStatistics();
            } else if (response.reaction == 2 && response.data.message === 'Token Expired, Please reload and try again.') {
                showErrorMessage('{{ __tr("Session expired. The page will refresh.") }}');
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                showErrorMessage(response.data.message || '{{ __tr("Failed to update order status") }}');
            }
        },
        error: function() {
            showErrorMessage('{{ __tr("Error updating order status") }}');
        },
        complete: function() {
            // Re-enable submit button
            $('#updateStatusForm button[type="submit"]').prop('disabled', false);
        }
    });
}

function refreshOrders() {
    loadOrders(currentPage);
    loadOrderStatistics();
}

function testLoadOrders() {
    console.log('Testing load orders...');
    console.log('Current filters:', currentFilters);
    console.log('Current page:', currentPage);
    
    // Test the route directly
    $.ajax({
        url: "{{ route('vendor.whatsapp.orders.data') }}",
        method: 'GET',
        data: 'page=1',
        success: function(response) {
            console.log('Test response:', response);
            alert('Test successful! Check console for details.');
        },
        error: function(xhr, status, error) {
            console.error('Test failed:', status, error);
            console.error('Response:', xhr.responseText);
            alert('Test failed! Check console for details.');
        }
    });
}

function loadOrderStatistics() {
    $.ajax({
        url: "{{ route('vendor.whatsapp.orders.statistics') }}",
        method: 'GET',
        success: function(response) {
            if (response.reaction == 1) {
                updateStatisticsCards(response.data.order_statistics);
            }
        },
        error: function() {
            console.log('Error loading order statistics');
        }
    });
}

function updateStatisticsCards(stats) {
    // Update the statistics cards with new data
    // Find cards by their content and update the h2 elements
    $('.card').each(function() {
        const card = $(this);
        const title = card.find('h3').text();
        
        if (title.includes('{{ __tr("Total Orders") }}')) {
            card.find('h2').text(stats.total_orders || 0);
        } else if (title.includes('{{ __tr("Pending Orders") }}')) {
            card.find('h2').text(stats.pending_orders || 0);
        } else if (title.includes('{{ __tr("Completed Orders") }}')) {
            card.find('h2').text(stats.completed_orders || 0);
        } else if (title.includes('{{ __tr("Total Revenue") }}')) {
            const totalRevenue = formatCurrency(stats.total_revenue || 0);
            card.find('h2').text(totalRevenue);
        } else if (title.includes('{{ __tr("Pending Revenue") }}')) {
            const pendingRevenue = formatCurrency(stats.pending_revenue || 0);
            card.find('h2').text(pendingRevenue);
        }
    });
}

function formatCurrency(amount) {
    // Format as INR currency
    return '₹' + new Intl.NumberFormat('en-IN').format(amount);
}

function clearFilters() {
    $('#ordersFilterForm')[0].reset();
    $('#entries-per-page').val('100'); // Reset to default
    currentFilters = '';
    currentPage = 1;
    loadOrders();
}

function exportOrders() {
    let params = '';
    if (currentFilters && typeof currentFilters === 'string') {
        params = currentFilters;
    }
    window.location.href = "{{ route('vendor.whatsapp.orders.export') }}?" + params;
}

function renderPagination(orders) {
    // Simple pagination implementation
    let html = '';
    if (orders.last_page > 1) {
        html += '<nav><ul class="pagination">';
        
        // Previous button
        if (orders.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadOrders(${orders.current_page - 1})">Previous</a></li>`;
        }
        
        // Page numbers
        for (let i = 1; i <= orders.last_page; i++) {
            if (i == orders.current_page) {
                html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="loadOrders(${i})">${i}</a></li>`;
            }
        }
        
        // Next button
        if (orders.current_page < orders.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadOrders(${orders.current_page + 1})">Next</a></li>`;
        }
        
        html += '</ul></nav>';
    }
    
    $('#ordersPagination').html(html);
}
</script>
@endpush
