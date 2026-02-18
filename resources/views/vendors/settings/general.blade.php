<!-- Page Heading -->
<h2>
    <?= __tr('General Settings') ?>
</h2>
<!-- Page Heading -->
<!-- General setting form -->
<div class="row">
</div>
<fieldset>
    <legend>{{ __tr('Basic Settings') }}</legend>
    <form class="lw-ajax-form lw-form" data-show-processing="true" method="post"
        action="<?= route('vendor.settings_basic.write.update') ?>">
        <!-- Vendor Name -->
        <div class="form-group">
            <label for="lwVendorName">
                <?= __tr('Vendor Title') ?>
            </label>
            <input type="text" class="form-control form-control-user" name="store_name" id="lwVendorName"
                value="{{ $basicSettings['title'] }}" required>
        </div>
        <!-- /Vendor Name -->
        <div class="col-12 text-right">
            <!-- Update Button -->
            <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">
                <?= __tr('Save') ?>
            </button>
            <!-- /Update Button -->
        </div>
    </form>
</fieldset>
<fieldset>
    <legend>{{ __tr('Business Information') }}</legend>
    <form class="lw-ajax-form lw-form" data-show-processing="true" method="post"
        action="<?= route('vendor.settings.write.update') ?>">
        <input type="hidden" name="pageType" value="{{ $pageType }}">
        {{-- address --}}
        <fieldset>
            <legend>{!! __tr('Address & Contact') !!}</legend>
            <div class="row">
                <div class="col-md-4">
                    <x-lw.input-field data-form-group-class="" name="address" :label="__tr('Address line')"
                        value="{{ $configurationData['address'] }}" required />
                </div>
                <div class="col-md-4">
                    <x-lw.input-field data-form-group-class="" name="postal_code" :label="__tr('Postal Code')"
                        value="{{ $configurationData['postal_code'] }}" required />
                </div>
                <div class="col-md-4">
                    <x-lw.input-field data-form-group-class="" name="city" :label="__tr('City')"
                        value="{{ $configurationData['city'] }}" required />
                </div>
                <div class="col-md-4">
                    <x-lw.input-field data-form-group-class="" name="state" :label="__tr('State')"
                        value="{{ $configurationData['state'] }}" required />
                </div>
                <div class="col-md-4">
                    <!-- Select country -->
                    <x-lw.input-field type="selectize" data-lw-plugin="lwSelectize" data-form-group-class=""
                        required="true" name="country" :label="__tr('Select Country')" required>
                        <x-slot name="selectOptions">
                            <option value="">{{ __tr('Select Country') }}</option>
                            @foreach ($configurationData['countries_list'] as $country)
                            <option value="<?= $country['id'] ?>" <?=$configurationData['country']==$country['id']
                                ? 'selected' : '' ?>>
                                <?= $country['name'] ?>
                            </option>
                            @endforeach
                        </x-slot>
                    </x-lw.input-field>
                    <!-- /Select country -->
                </div>
                <div class="col-md-4">
                    <x-lw.input-field data-form-group-class="" type="number" name="contact_phone"
                        :label="__tr('Business Phone')" value="{{ $configurationData['contact_phone'] }}" required />
                </div>
                <div class="col-md-4">
                    <!-- Contact Email -->
                    <x-lw.input-field id="lwContactEmail" data-form-group-class="" type="email" name="contact_email"
                        :label="__tr('Contact Email')" value="{{ $configurationData['contact_email'] }}" required />
                    <!-- /Contact Email -->
                </div>
        </fieldset>
        <fieldset>
            <legend>{{ __tr('Other') }}</legend>
            <div class="row">
                <!-- Select Timezone -->
                <x-lw.input-field type="selectize" data-form-group-class="col-md-4" name="timezone"
                    :label="__tr('Select Timezone')" data-selected="{{ getVendorSettings('timezone') }}" required>
                    <x-slot name="selectOptions">
                        @foreach ($configurationData['timezone_list'] as $timezone)
                        <option value="<?= $timezone['value'] ?>">
                            <?= $timezone['text'] ?>
                        </option>
                        @endforeach
                    </x-slot>
                </x-lw.input-field>
                <!-- /Select Timezone -->
                <!-- Select Default language -->
                <x-lw.input-field type="selectize" data-form-group-class="col-md-4" name="default_language"
                    :label="__tr('Default Language')"  data-selected="{{ getVendorSettings('default_language') }}" placeholder="{{ __tr('Select default language') }}" required>
                    <x-slot name="selectOptions">
                        @if (!__isEmpty($configurationData['languageList']))
                        @foreach ($configurationData['languageList'] as $key => $language)
                        <option value="<?= $language['id'] ?>">
                            <?= $language['name'] ?>
                        </option>
                        @endforeach
                        @endif
                    </x-slot>
                </x-lw.input-field>
                <!-- /Select Default language -->
            </div>
        </fieldset>
        <div class="row">
            <div class="col-12 text-right mt-5">
                <!-- Update Button -->
                <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">
                    <?= __tr('Save') ?>
                </button>
                <!-- /Update Button -->
            </div>
        </div>
    </form>
</fieldset>
<fieldset>
    <legend>{{ __tr('System Settings') }}</legend>
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
        <label class="mb-0">{{ __tr('User Temp Uploads') }}</label>
        <form method="get" action="<?= route('vendor.settings.read', ['pageType' => 'general']) ?>">
            <div class="form-row align-items-end">
                <div class="col-auto">
                    <label class="mb-1">{{ __tr('From Date') }}</label>
                    <input type="date" class="form-control" name="from_date"
                        value="{{ $tempUploadsFilters['from_date'] ?? '' }}" />
                </div>
                <div class="col-auto">
                    <label class="mb-1">{{ __tr('To Date') }}</label>
                    <input type="date" class="form-control" name="to_date"
                        value="{{ $tempUploadsFilters['to_date'] ?? '' }}" />
                </div>
                <div class="col-auto d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">{{ __tr('Apply Filter') }}</button>
                    <a href="<?= route('vendor.settings.read', ['pageType' => 'general']) ?>" class="btn btn-light">
                        {{ __tr('Clear') }}
                    </a>
                    <button type="button" id="lwOpenDeleteVendorTempUploadsConfirm" class="btn btn-danger btn-user lw-btn-block-mobile ml-2">
                        {{ __tr('Delete Selected') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
    <form id="lwVendorTempUploadsForm" class="lw-ajax-form lw-form" data-show-processing="true" method="post"
        action="<?= route('vendor.settings.temp_uploads.delete') ?>">
        <div class="form-group">
            @if (!__isEmpty($tempUploads))
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th style="width:40px;">
                                    <input type="checkbox" id="lwSelectAllTempUploads" />
                                </th>
                                <th>{{ __tr('File') }}</th>
                                <th style="width:140px;">{{ __tr('Size') }} : {{ $tempUploadsTotalSize ?? '0 bytes' }}</th>
                                <th style="width:180px;">{{ __tr('Last Modified') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tempUploads as $tempUpload)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="lw-temp-upload-checkbox" name="temp_uploads[]"
                                            value="{{ $tempUpload['name'] }}" />
                                    </td>
                                    <td>{{ $tempUpload['name'] }}</td>
                                    <td>{{ $tempUpload['size_readable'] }}</td>
                                    <td>{{ $tempUpload['modified'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info mb-0">
                    {{ __tr('No temp uploads found for this user.') }}
                </div>
            @endif
        </div>
    </form>

    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
        <label class="mb-0">{{ __tr('WhatsApp Media') }}</label>
        <form method="get" action="<?= route('vendor.settings.read', ['pageType' => 'general']) ?>">
            <div class="form-row align-items-end">
                <div class="col-auto">
                    <label class="mb-1">{{ __tr('From Date') }}</label>
                    <input type="date" class="form-control" name="vendor_media_from_date"
                        value="{{ $vendorMediaFilters['from_date'] ?? '' }}" />
                </div>
                <div class="col-auto">
                    <label class="mb-1">{{ __tr('To Date') }}</label>
                    <input type="date" class="form-control" name="vendor_media_to_date"
                        value="{{ $vendorMediaFilters['to_date'] ?? '' }}" />
                </div>
                <div class="col-auto">
                    <label class="mb-1">{{ __tr('Items') }}</label>
                    <select class="form-control" name="vendor_media_per_page">
                        @foreach ([10, 20, 50, 100, 500, 1000] as $size)
                            <option value="{{ $size }}"
                                {{ (int) ($vendorMediaFilters['per_page'] ?? 10) === $size ? 'selected' : '' }}>
                                {{ $size }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto d-flex align-items-end">
                    <input type="hidden" name="vendor_media_page" value="1" />
                    <button type="submit" class="btn btn-primary mr-2">{{ __tr('Apply Filter') }}</button>
                    <a href="<?= route('vendor.settings.read', ['pageType' => 'general']) ?>" class="btn btn-light">
                        {{ __tr('Clear') }}
                    </a>
                </div>
            </div>
        </form>
    </div>
    <form id="lwVendorMediaForm" class="lw-ajax-form lw-form" data-show-processing="true" method="post"
        action="<?= route('vendor.settings.vendor_media.delete') ?>">
        <div class="form-group">
            @if (!__isEmpty($vendorMediaUploads))
                @php
                    $vendorMediaPagination = $vendorMediaPagination ?? [];
                    $vendorMediaPage = (int) ($vendorMediaPagination['page'] ?? 1);
                    $vendorMediaPerPage = (int) ($vendorMediaPagination['per_page'] ?? ($vendorMediaFilters['per_page'] ?? 10));
                    $vendorMediaTotalCount = (int) ($vendorMediaPagination['total_count'] ?? count($vendorMediaUploads));
                    $vendorMediaTotalPages = (int) ($vendorMediaPagination['total_pages'] ?? 1);
                    $vendorMediaFrom = $vendorMediaTotalCount ? (($vendorMediaPage - 1) * $vendorMediaPerPage + 1) : 0;
                    $vendorMediaTo = min($vendorMediaPage * $vendorMediaPerPage, $vendorMediaTotalCount);
                    $vendorMediaStart = max(1, $vendorMediaPage - 2);
                    $vendorMediaEnd = min($vendorMediaTotalPages, $vendorMediaPage + 2);
                @endphp
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th style="width:40px;">
                                    <input type="checkbox" id="lwSelectAllVendorMedia" />
                                </th>
                                <th style="width:140px;">{{ __tr('Type') }}</th>
                                <th>{{ __tr('File') }}</th>
                                <th style="width:160px;">{{ __tr('Size') }} : {{ $vendorMediaTotalSize ?? '0 bytes' }}</th>
                                <th style="width:180px;">{{ __tr('Last Modified') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vendorMediaUploads as $media)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="lw-vendor-media-checkbox" name="vendor_media[]"
                                            value="{{ $media['type'] }}|{{ $media['file'] }}" />
                                    </td>
                                    <td>{{ ucfirst($media['type']) }}</td>
                                    <td>{{ $media['file'] }}</td>
                                    <td>{{ $media['size_readable'] }}</td>
                                    <td>{{ $media['modified'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div class="text-muted small mb-2">
                        {{ __tr('Showing __from__-__to__ of __total__', [
                            '__from__' => $vendorMediaFrom,
                            '__to__' => $vendorMediaTo,
                            '__total__' => $vendorMediaTotalCount,
                        ]) }}
                    </div>
                    @if ($vendorMediaTotalPages > 1)
                        <nav>
                            <ul class="pagination mb-2">
                                <li class="page-item {{ $vendorMediaPage <= 1 ? 'disabled' : '' }}">
                                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['vendor_media_page' => max(1, $vendorMediaPage - 1)]) }}">
                                        {{ __tr('Previous') }}
                                    </a>
                                </li>
                                @for ($i = $vendorMediaStart; $i <= $vendorMediaEnd; $i++)
                                    <li class="page-item {{ $vendorMediaPage === $i ? 'active' : '' }}">
                                        <a class="page-link" href="{{ request()->fullUrlWithQuery(['vendor_media_page' => $i]) }}">
                                            {{ $i }}
                                        </a>
                                    </li>
                                @endfor
                                <li class="page-item {{ $vendorMediaPage >= $vendorMediaTotalPages ? 'disabled' : '' }}">
                                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['vendor_media_page' => min($vendorMediaTotalPages, $vendorMediaPage + 1)]) }}">
                                        {{ __tr('Next') }}
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    @endif
                </div>
                <div class="text-right">
                    <button type="button" id="lwOpenDeleteVendorMediaConfirm"
                        class="btn btn-danger btn-user lw-btn-block-mobile">
                        {{ __tr('Delete Selected') }}
                    </button>
                </div>
            @else
                <div class="alert alert-info mb-0">
                    {{ __tr('No vendor media found.') }}
                </div>
            @endif
        </div>
    </form>
</fieldset>

<script type="text/template" id="lwDeleteVendorTempUploads-template">
    <h2>{{ __tr('Are You Sure!') }}</h2>
    <p>{{ __tr('You want to delete selected temp files?') }}</p>
</script>
<script type="text/template" id="lwDeleteVendorMedia-template">
    <h2>{{ __tr('Are You Sure!') }}</h2>
    <p>{{ __tr('You want to delete selected vendor media?') }}</p>
</script>
@if (getAppSettings('pusher_by_vendor'))
<!--Pusher key -->
<fieldset id="pusherKeysConfiguration">
    <legend>{{ __tr('Pusher - required for realtime updates') }}</legend>
    <form class="lw-ajax-form lw-form" method="post" action="<?= route('vendor.settings.write.update') ?>" x-cloak
        x-data="{pusherSettingsExists: {{ getVendorSettings('pusher_app_id') ? 1 : 0 }}}">
        <input type="hidden" name="pageType" value="pusher">
        <div x-show="pusherSettingsExists"></div>
        <div class="form-group" x-cloak x-show="pusherSettingsExists">
            <div class="btn-group">
                <button type="button" disabled="true" class="btn btn-success lw-btn">
                    {{ __tr('Pusher Settings are exist') }}
                </button>
                <button type="button" @click="pusherSettingsExists = !pusherSettingsExists"
                    class="btn btn-light lw-btn">{{ __tr('Update') }}</button>
            </div>
        </div>
        <div x-show="!pusherSettingsExists" >
            <div class="col-sm-12 col-md-6 col-lg-4">
            <x-lw.input-field type="text" id="lwPusherAppId" data-form-group-class="" :label="__tr('App ID')"
                name="pusher_app_id" required="true" />
            <x-lw.input-field type="text" id="lwPusherKey" data-form-group-class="" :label="__tr('App Key')"
                name="pusher_app_key" required="true" />
            <x-lw.input-field type="text" id="lwPusherAppSecret" data-form-group-class="" :label="__tr('App Secret')"
                name="pusher_app_secret" required="true" />
            <x-lw.input-field type="text" id="lwPusherAppCluster" data-form-group-class="" :label="__tr('App Cluster')"
                name="pusher_app_cluster" required="true" />
            </div>
            <!--  Button -->
            <div class="col-sm-12 col-md-6 col-lg-4 mt-3">
                    <!-- Update Button -->
                    <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">
                        <?= __tr('Save') ?>
                    </button>
                    <!-- /Update Button -->
            </div>
            <!-- / Button -->
        </div>
    </form>
</fieldset>
<!--/Pusher key -->
@endif

<!-- /General setting form -->
@push('appScripts')
<script>
    (function($) {
        'use strict';
        // After file successfully uploaded then this function is called
        window.afterUploadedFile = function (responseData) {
            var requestData = responseData.data;
            $('#lwUploadedLogo').attr('src', requestData.path);
        }

        $('#lwSelectAllTempUploads').on('change', function() {
            $('.lw-temp-upload-checkbox').prop('checked', $(this).is(':checked'));
        });

        $('#lwSelectAllVendorMedia').on('change', function() {
            $('.lw-vendor-media-checkbox').prop('checked', $(this).is(':checked'));
        });

        $(document).on('click', '#lwOpenDeleteVendorTempUploadsConfirm', function(e) {
            e.preventDefault();
            if ($('.lw-temp-upload-checkbox:checked').length < 1) {
                showWarnMessage("{{ __tr('Please select at least one file.') }}");
                return;
            }
            showConfirmation('#lwDeleteVendorTempUploads-template', function () {
                $('#lwVendorTempUploadsForm').trigger('submit');
            });
        });

        $(document).on('click', '#lwOpenDeleteVendorMediaConfirm', function(e) {
            e.preventDefault();
            if ($('.lw-vendor-media-checkbox:checked').length < 1) {
                showWarnMessage("{{ __tr('Please select at least one file.') }}");
                return;
            }
            showConfirmation('#lwDeleteVendorMedia-template', function () {
                $('#lwVendorMediaForm').trigger('submit');
            });
        });
    })(jQuery);
</script>
@endpush