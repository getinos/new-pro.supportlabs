<!-- Page Heading -->
<h1><?= __tr('General Settings') ?></h1>
<!-- Page Heading -->
<hr>
<!-- General setting form -->
<form class="lw-ajax-form lw-form" method="post" action="<?= route('manage.configuration.write', ['pageType' => request()->pageType]) ?>">
<div class="row">
</div>
    <div class="">
        <div class="col-12">
            <div class="alert alert-default">
                {{  __tr('Upload will be processed automatically on valid selection.') }}
            </div>
        </div>
        <div class=" row col-lg-12 ">
             <!-- upload logo -->
            <div class="form-group float-left mr-5">
                <label for="lwUploadLogo"><?= __tr('Logo') ?></label>
            <input type="file" data-lw-plugin="lwUploader" data-label-idle="{{ __tr('Select New Logo') }}" data-allow-revert="true" data-instant-upload="true" data-action="<?= route('media.upload_logo') ?>" id="lwUploadLogo" data-callback="afterUploadedFile" data-default-image-url="<?= getAppSettings('logo_image_url') ?>" data-file-input-element="#lwLogoNameHidden">
            <!-- Hidden field to track logo name for form submission -->
            <input type="hidden" name="logo_name" id="lwLogoNameHidden" value="<?= $configurationData['logo_name'] ?? '' ?>">
            <small class="form-text text-muted">{{ __tr('Recommended size: 930x240 pixels | Format:png') }}</small>    
        </div>
             <!-- /upload logo -->
              <!-- upload small logo -->
            <div class="form-group mr-5">
                <label for="lwUploadSmallLogo"><?= __tr('Small Logo') ?></label>
            <input type="file" data-lw-plugin="lwUploader" data-label-idle="{{ __tr('Select New Small Logo') }}" data-allow-revert="true" data-instant-upload="true" data-action="<?= route('media.upload_small_logo') ?>" id="lwUploadSmallLogo" data-callback="afterUploadedFile" data-default-image-url="<?= getAppSettings('small_logo_image_url') ?>" data-file-input-element="#lwSmallLogoNameHidden">
            <!-- Hidden field to track small logo name for form submission -->
            <input type="hidden" name="small_logo_name" id="lwSmallLogoNameHidden" value="<?= $configurationData['small_logo_name'] ?? '' ?>">
            <small class="form-text text-muted">{{ __tr('Recommended size: 520x460 pixels | Format:png') }}</small>    
        </div>
             <!-- /upload small logo -->
              <!-- upload favicon -->
            <div class="form-group float-right">
                <label for="lwUploadFavicon"><?= __tr('Favicon') ?></label>
                <input type="file" data-lw-plugin="lwUploader" data-label-idle="{{ __tr('Select New Favicon') }}" data-instant-upload="true" data-action="<?= route('media.upload_favicon') ?>" data-callback="afterUploadedFile" id="lwUploadFavicon" data-default-image-url="<?= getAppSettings('favicon_image_url') ?>" data-file-input-element="#lwFaviconNameHidden">
                <!-- Hidden field to track favicon name for form submission -->
                <input type="hidden" name="favicon_name" id="lwFaviconNameHidden" value="<?= $configurationData['favicon_name'] ?? '' ?>">
                <small class="form-text text-muted">{{ __tr('Recommended size: 520x460 pixels ') }}</small>
                <small class="form-text text-muted">{{ __tr(' Format:png') }}</small>
            </div>
             <!-- /upload favicon -->
        </div>
    </div>
    <hr>
    <!-- Website Name -->
    <div class="form-group">
        <label for="lwWebsiteName"><?= __tr('Your Website Name') ?></label>
        <input type="text" class="form-control form-control-user" name="name" id="lwWebsiteName" value="<?= $configurationData['name'] ?>" required>
    </div>
    <!-- /Website Name -->
    <!-- Website Description -->
    <div class="form-group">
        <label for="lwWebsiteDescription"><?= __tr('Your Website Description') ?></label>
        <textarea name="description" id="lwWebsiteDescription" class="form-control" rows="2"><?= $configurationData['description'] ?></textarea>
    </div>
    <!-- /Website Description -->

    <fieldset>
        <legend>{{  __tr('Contact Settings') }}</legend>
            <!-- Contact Email -->
        <div class="form-group">
            <label for="lwContactEmail"><?= __tr('Contact Email') ?></label>
            <input type="email" class="form-control form-control-user" name="contact_email" id="lwContactEmail" value="<?= $configurationData['contact_email'] ?>">
            <small class="help-text">{{  __tr('It will be used to receive contact form emails') }}</small>
        </div>
        <!-- /Contact Email -->
        <!-- Contact details -->
        <div class="form-group">
            <label for="lwContactDetails"><?= __tr('Contact Details') ?></label>
            <textarea class="form-control form-control-user" name="contact_details" rows="4" id="lwContactDetails">{!! $configurationData['contact_details'] !!}</textarea>
            <small class="help-text">{{  __tr('Details added here will be shown on contact page') }}</small>
        </div>
        <!-- /Contact details -->
    </fieldset>

    <fieldset>
        <legend>{{  __tr('Additional Settings') }}</legend>
            <!-- Contact Phone -->
        
        <!-- /Contact Phone -->
       <!-- Demo Video Link -->
       <div class="form-group">
            <label for="lwDemoVideoLink"><?= __tr('Demo Video Link') ?></label>
            <input type="text" class="form-control form-control-user" name="demo_video_link" id="lwDemoVideoLink" value="<?= $configurationData['demo_video_link'] ?>">
            <small class="help-text">{{  __tr('It will be used to display Demo Video button in Homepage ') }}</small>
        </div>
        <!-- /Demo Video Link -->
         <!-- WhatsApp Demo Link -->
       <div class="form-group">
            <label for="lwWhatsAppDemoLink"><?= __tr('WhatsApp Demo Link') ?></label>
            <input type="text" class="form-control form-control-user" name="whatsapp_demo_link" id="lwWhatsAppDemoLink" value="<?= $configurationData['whatsapp_demo_link'] ?>">
            <small class="help-text">{{  __tr('It will be used to add link for Book Demo Button in Homepage ') }}</small>
        </div>
        <!-- /WhatsApp Demo Link -->
          <!-- Hero Image Upload -->
          <div class="form-group col-lg-6 px-0">
                <label for="lwUploadHeroImage"><?= __tr('Hero Image') ?></label>
                <input type="file" 
                    data-lw-plugin="lwUploader" 
                    data-label-idle="{{ __tr('Select New Hero Image') }}" 
                    data-instant-upload="true" 
                    data-action="<?= route('media.upload_hero_image') ?>" 
                    data-callback="afterUploadedFile" 
                    id="lwUploadHeroImage" 
                    data-default-image-url="<?= getAppSettings('hero_image') ?>">
                <small class="form-text text-muted">{{ __tr('Recommended size: 1200x1200 pixels | Format: .png') }}</small>
            </div>
             <!-- /Hero Image Upload -->

             <!-- WhatsApp QR Code Upload -->
             <div class="form-group col-lg-6 px-0">
                 <label for="lwUploadWhatsAppQR"><?= __tr('WhatsApp QR Code') ?></label>
                 <input type="file" 
                     data-lw-plugin="lwUploader" 
                     data-label-idle="{{ __tr('Select WhatsApp QR Code') }}" 
                     data-instant-upload="true" 
                     data-action="<?= route('media.upload_whatsapp_qr') ?>" 
                     data-callback="afterUploadedFile" 
                     id="lwUploadWhatsAppQR" 
                     data-default-image-url="<?= getAppSettings('whatsapp_qr_image') ?>">
                 <small class="form-text text-muted">{{ __tr('Upload your WhatsApp QR code image for easy scanning') }}</small>
             </div>
             <!-- /WhatsApp QR Code Upload -->
    </fieldset>

    <fieldset>
        <legend>{{  __tr('Localization') }}</legend>
        <!-- Select Timezone -->
    <div class="form-group">
        <label for="lwSelectTimezone"><?= __tr('Select Timezone') ?></label>
        <select data-lw-plugin="lwSelectize" data-label-field="name" data-selected="{{ $configurationData['timezone'] }}" data-search-field="{{ json_encode(['id','name']) }}" data-value-field="id" id="lwSelectTimezone" class="form-control form-control-user" name="timezone" required>
            @foreach($configurationData['timezone_list'] as $timezone)
            <option value="<?= $timezone['value'] ?>"><?= $timezone['text'] ?></option>
            @endforeach
        </select>
    </div>
    <!-- /Select Timezone -->

    <!-- Select Default language -->
    <div class="form-group mt-2">
        <label for="lwSelectDefaultLanguage"><?= __tr('Default Language') ?></label>
        <select id="lwSelectDefaultLanguage" data-lw-plugin="lwSelectize" placeholder="Default Language..." name="default_language">
            @if(!__isEmpty($configurationData['languageList']))
            @foreach($configurationData['languageList'] as $key => $language)
            <option value="<?= $language['id'] ?>" <?= $configurationData['default_language'] == $language['id'] ? 'selected' : '' ?> required><?= $language['name'] ?></option>
            @endforeach
            @endif
        </select>
    </div>
    <!-- /Select Default language -->
    </fieldset>

    <!-- Update Button -->
   <div class="mt-4">
    <a href class="lw-ajax-form-submit-action btn btn-primary btn-user lw-btn-block-mobile">
        <?= __tr('Save') ?>
    </a>
   </div>
    <!-- /Update Button -->

    <div class="mt-4">
        <hr>
        <h5><?= __tr('Maintenance') ?></h5>
        <p class="text-muted mb-2"><?= __tr('Generate a short-lived migration token (super admin only). Token is logged and shown once.') ?></p>
        <button type="button" class="btn btn-warning" id="lwGenerateMigrationTokenBtn">
            <?= __tr('Generate Migration Token') ?>
        </button>
        <div class="mt-2" id="lwMigrationTokenResult" style="display:none;">
            <div class="alert alert-info mb-0" id="lwMigrationTokenAlert"></div>
        </div>
        <div class="mt-3" id="lwRunMigrationWrapper" style="display:none;">
            <button type="button" class="btn btn-danger" id="lwRunMigrationBtn">
                <?= __tr('Run Migration') ?>
            </button>
            <div class="small text-muted mt-1"><?= __tr('Visible after generating a token') ?></div>
        </div>
    </div>
</form>
<!-- /General setting form -->

<fieldset class="mt-4">
    <legend>{{ __tr('System Settings') }}</legend>
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
        <label class="mb-0">{{ __tr('User Temp Uploads') }}</label>
        <form method="get" action="<?= route('manage.configuration.read', ['pageType' => 'general']) ?>">
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
                    <a href="<?= route('manage.configuration.read', ['pageType' => 'general']) ?>" class="btn btn-light">
                        {{ __tr('Clear') }}
                    </a>
                    <button type="button" id="lwOpenDeleteSystemTempUploadsConfirm" class="btn btn-danger btn-user lw-btn-block-mobile ml-2">
                        {{ __tr('Delete Selected') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
    <form id="lwSystemTempUploadsForm" class="lw-ajax-form lw-form" data-show-processing="true" method="post"
        action="<?= route('manage.configuration.temp_uploads.delete') ?>">
        <div class="form-group">
            @if (!__isEmpty($tempUploads))
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th style="width:40px;">                                        <input type="checkbox" id="lwSelectAllSystemTempUploads" class="ml-2" />
</th>
                                <th style="width:220px;">
                                    <div class="d-flex align-items-center">
                                        <span>{{ __tr('User') }}</span>
                                    </div>
                                </th>
                                <th style="width:260px;">{{ __tr('User UID') }}</th>
                                <th>{{ __tr('File') }}</th>
                                <th style="width:140px;">{{ __tr('Size') }} : {{ $tempUploadsTotalSize ?? '0 bytes' }}</th>
                                <th style="width:180px;">{{ __tr('Last Modified') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tempUploads as $tempUpload)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="lw-system-temp-upload-checkbox" name="temp_uploads[]"
                                            value="{{ $tempUpload['uid'] }}|{{ $tempUpload['file'] }}" />
                                    </td>
                                    <td>
                                        @if (!empty($tempUpload['user_active']) && !empty($tempUpload['user_name']))
                                            {{ $tempUpload['user_name'] }}
                                        @else
                                            <span class="text-muted">{{ __tr('No active user') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $tempUpload['uid'] }}</td>
                                    <td>{{ $tempUpload['file'] }}</td>
                                    <td>{{ $tempUpload['size_readable'] }}</td>
                                    <td>{{ $tempUpload['modified'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info mb-0">
                    {{ __tr('No temp uploads found.') }}
                </div>
            @endif
        </div>
    </form>
</fieldset>

<script type="text/template" id="lwDeleteSystemTempUploads-template">
    <h2>{{ __tr('Are You Sure!') }}</h2>
    <p>{{ __tr('You want to delete selected temp files?') }}</p>
</script>

<fieldset class="mt-4">
    <legend>{{ __tr('Vendor Media') }}</legend>
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
        <label class="mb-0">{{ __tr('WhatsApp Media') }}</label>
        <form method="get" action="<?= route('manage.configuration.read', ['pageType' => 'general']) ?>">
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
                    <a href="<?= route('manage.configuration.read', ['pageType' => 'general']) ?>" class="btn btn-light">
                        {{ __tr('Clear') }}
                    </a>
                    <button type="button" id="lwOpenDeleteVendorMediaConfirm" class="btn btn-danger btn-user lw-btn-block-mobile ml-2">
                        {{ __tr('Delete Selected') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
    <form id="lwVendorMediaForm" class="lw-ajax-form lw-form" data-show-processing="true" method="post"
        action="<?= route('manage.configuration.vendor_media.delete') ?>">
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
                                <th style="width:220px;">{{ __tr('Vendor') }}</th>
                                <th style="width:260px;">{{ __tr('Vendor UID') }}</th>
                                <th style="width:120px;">{{ __tr('Type') }}</th>
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
                                            value="{{ $media['vendor_uid'] }}|{{ $media['type'] }}|{{ $media['file'] }}" />
                                    </td>
                                    <td>
                                        @if (!empty($media['vendor_active']) && !empty($media['vendor_name']))
                                            {{ $media['vendor_name'] }}
                                        @else
                                            <span class="text-muted">{{ __tr('No active vendor') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $media['vendor_uid'] }}</td>
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
            @else
                <div class="alert alert-info mb-0">
                    {{ __tr('No vendor media found.') }}
                </div>
            @endif
        </div>
    </form>
</fieldset>

<script type="text/template" id="lwDeleteVendorMedia-template">
    <h2>{{ __tr('Are You Sure!') }}</h2>
    <p>{{ __tr('You want to delete selected vendor media?') }}</p>
</script>

@push('appScripts')
<script>
    (function($) {
        'use strict';
    // After file successfully uploaded then this function is called
    window.afterUploadedFile = function (responseData) {
        var requestData = responseData.data;
        $('#lwUploadedLogo').attr('src', requestData.path);
        
        // Update hidden fields when files are uploaded
        // This is handled by data-file-input-element, but we ensure it works here too
        if (requestData.fileName) {
            // The FilePond plugin should have already updated the hidden field
            // via data-file-input-element, but we'll verify it's set
            console.log('File uploaded:', requestData.fileName);
        }
    }

    // Handle file removal for logo, small logo, and favicon
    // This will be triggered when user clicks the cross (X) icon
    $(document).ready(function() {
        // Wait a bit for FilePond to initialize
        setTimeout(function() {
            // Get FilePond instances if they exist
            var logoPond = window.FilePond && document.querySelector('#lwUploadLogo') ? 
                window.FilePond.find(document.querySelector('#lwUploadLogo')) : null;
            var smallLogoPond = window.FilePond && document.querySelector('#lwUploadSmallLogo') ? 
                window.FilePond.find(document.querySelector('#lwUploadSmallLogo')) : null;
            var faviconPond = window.FilePond && document.querySelector('#lwUploadFavicon') ? 
                window.FilePond.find(document.querySelector('#lwUploadFavicon')) : null;

            // Add onremovefile handler for logo
            if (logoPond) {
                logoPond.on('removefile', function(error, file) {
                    if (!error) {
                        $('#lwLogoNameHidden').val(''); // Clear logo name to mark for deletion
                        console.log('Logo removed - will be deleted from DB on save');
                    }
                });
            }

            // Add onremovefile handler for small logo
            if (smallLogoPond) {
                smallLogoPond.on('removefile', function(error, file) {
                    if (!error) {
                        $('#lwSmallLogoNameHidden').val(''); // Clear small logo name to mark for deletion
                        console.log('Small logo removed - will be deleted from DB on save');
                    }
                });
            }

            // Add onremovefile handler for favicon
            if (faviconPond) {
                faviconPond.on('removefile', function(error, file) {
                    if (!error) {
                        $('#lwFaviconNameHidden').val(''); // Clear favicon name to mark for deletion
                        console.log('Favicon removed - will be deleted from DB on save');
                    }
                });
            }
        }, 1000); // Wait 1 second for FilePond to initialize

        // Also listen for the custom FilePond:removefile event as a fallback
        document.addEventListener('FilePond:removefile', function(e) {
            var pond = e.detail.pond;
            var elementId = pond.element ? pond.element.id : '';
            
            // Check which uploader was used and clear the corresponding hidden field
            if (elementId === 'lwUploadLogo') {
                $('#lwLogoNameHidden').val(''); // Clear logo name to mark for deletion
                console.log('Logo removed (via event) - will be deleted on save');
            } else if (elementId === 'lwUploadSmallLogo') {
                $('#lwSmallLogoNameHidden').val(''); // Clear small logo name to mark for deletion
                console.log('Small logo removed (via event) - will be deleted on save');
            } else if (elementId === 'lwUploadFavicon') {
                $('#lwFaviconNameHidden').val(''); // Clear favicon name to mark for deletion
                console.log('Favicon removed (via event) - will be deleted on save');
            }
        });
    });

    $(document).on('change', '#lwSelectAllSystemTempUploads', function() {
        $('.lw-system-temp-upload-checkbox').prop('checked', $(this).is(':checked'));
    });

    $(document).on('click', '#lwOpenDeleteSystemTempUploadsConfirm', function(e) {
        e.preventDefault();
        if ($('.lw-system-temp-upload-checkbox:checked').length < 1) {
            showWarnMessage("{{ __tr('Please select at least one file.') }}");
            return;
        }
        showConfirmation('#lwDeleteSystemTempUploads-template', function () {
            $('#lwSystemTempUploadsForm').trigger('submit');
        });
    });

    $(document).on('change', '#lwSelectAllVendorMedia', function() {
        $('.lw-vendor-media-checkbox').prop('checked', $(this).is(':checked'));
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

    // Store migration token in memory
    let migrationToken = null;

    $('#lwGenerateMigrationTokenBtn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        $btn.prop('disabled', true).text("<?= __tr('Generating...') ?>");
        $('#lwMigrationTokenResult').hide();
        $('#lwRunMigrationWrapper').hide();
        migrationToken = null;

        const csrfToken =
            $('meta[name="csrf-token"]').attr('content')
            || (typeof window !== 'undefined' && window.lwData && window.lwData.csrf_token)
            || (typeof window !== 'undefined' && window.lwData && window.lwData.csrfToken);
        if (!csrfToken) {
            $('#lwMigrationTokenAlert').html("<?= __tr('CSRF token not found. Please refresh and try again.') ?>");
            $('#lwMigrationTokenResult').show();
            $btn.prop('disabled', false).text("<?= __tr('Generate Migration Token') ?>");
            return;
        }

        $.ajax({
            url: "<?= route('manage.migration.generate_token') ?>",
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            success: function(response) {
                const data = response.data || {};
                migrationToken = data.token || null;
                const msg = `${"<?= __tr('Token') ?>"}: ${data.token || '---'}<br>${"<?= __tr('Expires at') ?>"}: ${data.expires_at || '---'}`;
                $('#lwMigrationTokenAlert')
                    .removeClass('alert-danger alert-success')
                    .addClass('alert-info')
                    .html(msg);
                $('#lwMigrationTokenResult').show();
                if (migrationToken) {
                    $('#lwRunMigrationWrapper').show();
                }
            },
            error: function(xhr) {
                let message = "<?= __tr('Unable to generate token.') ?>";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                $('#lwMigrationTokenAlert')
                    .removeClass('alert-info alert-success')
                    .addClass('alert-danger')
                    .html('<strong class="text-danger">' + message + '</strong>');
                $('#lwMigrationTokenResult').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text("<?= __tr('Generate Migration Token') ?>");
            }
        });
    });

    // Run migration with token
    $('#lwRunMigrationBtn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        
        if (!migrationToken) {
            alert("<?= __tr('Please generate a migration token first.') ?>");
            return;
        }

        if (!confirm("<?= __tr('Are you sure you want to run database migrations? This action cannot be undone.') ?>")) {
            return;
        }

        $btn.prop('disabled', true).text("<?= __tr('Running Migration...') ?>");

        const csrfToken =
            $('meta[name="csrf-token"]').attr('content')
            || (typeof window !== 'undefined' && window.lwData && window.lwData.csrf_token)
            || (typeof window !== 'undefined' && window.lwData && window.lwData.csrfToken);

        $.ajax({
            url: "<?= route('manage.migration.run') ?>",
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            data: {
                token: migrationToken
            },
            success: function(response) {
                const msg = response.message || "<?= __tr('Migration completed successfully.') ?>";
                const nothingToMigrate = response.data && response.data.nothing_to_migrate;
                const output = response.data && response.data.output ? 
                    '<pre style="max-height: 300px; overflow-y: auto; background: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 12px;">' + 
                    escapeHtml(response.data.output) + '</pre>' : '';
                
                // Use different styling based on result
                const alertClass = nothingToMigrate ? 'alert-info' : 'alert-success';
                const messageClass = nothingToMigrate ? 'text-info' : 'text-success';
                
                $('#lwMigrationTokenAlert')
                    .removeClass('alert-info alert-success alert-danger')
                    .addClass(alertClass)
                    .html('<strong class="' + messageClass + '">' + msg + '</strong>' + (output ? '<br>' + output : ''));
                $('#lwMigrationTokenResult').show();
                migrationToken = null; // Token is consumed
                $('#lwRunMigrationWrapper').hide();
            },
            error: function(xhr) {
                let message = "<?= __tr('Migration failed.') ?>";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                $('#lwMigrationTokenAlert')
                    .removeClass('alert-info alert-success')
                    .addClass('alert-danger')
                    .html('<strong class="text-danger">' + message + '</strong>');
                $('#lwMigrationTokenResult').show();
                migrationToken = null; // Token is consumed/invalid
                $('#lwRunMigrationWrapper').hide();
            },
            complete: function() {
                $btn.prop('disabled', false).text("<?= __tr('Run Migration') ?>");
            }
        });
    });

    // Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    })(jQuery);
</script>
@endpush