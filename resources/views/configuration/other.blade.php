<!-- Page Heading -->
<section>
    <h1>{!! __tr('Setup & Integrations') !!}</h1>
    <fieldset x-data="{panelOpened:false}" x-cloak>
        <legend @click="panelOpened = !panelOpened">{{ __tr('Campaign Execution Settings') }} <span class="text-danger my-2">{{ __tr('* required') }}</span> <small class="text-muted">{{  __tr('Click to expand/collapse') }}</small></legend>
        <form x-show="panelOpened" class="lw-ajax-form lw-form" method="post" action="<?= route('manage.configuration.write', ['pageType' => 'misc_settings']) ?>">
            <div>
                <x-lw.input-field data-form-group-class="col-lg-4 col-sm-6 col-md-3" type="number" min="0" :label="__tr('Number of Messages per lot for campaigns')" name="cron_process_messages_per_lot" value="{{ getAppSettings('cron_process_messages_per_lot') }}" required />
            <div class="col text-sm text-info">
                {{ __tr('Based on your server capacity you can set how many messages should be processed every 5 Seconds (cron job) OR per lot for queue job or when CRON URL requests executes for the Campaign Messages.') }}
            </div>
            </div>
            <hr class="my-4">
            <fieldset class="col mt-4" x-cloak x-data="{enable_queue_jobs_for_campaigns:'{{ getAppSettings('enable_queue_jobs_for_campaigns') }}'}">
                <legend>{{ __tr('Cron or Queue Job Setup') }} <span class="text-danger my-2">{{ __tr('* required for the campaigns to run as per schedule') }}</span></legend>
                <div class="form-group">
                    <label for="enableQueueJobsForCampaigns">
                        <x-lw.checkbox id="enableQueueJobsForCampaigns" data-secondary-color="#faab43" @click="(enable_queue_jobs_for_campaigns = !enable_queue_jobs_for_campaigns)" name="enable_queue_jobs_for_campaigns" :offValue="0" data-lw-plugin="lwSwitchery" :checked="getAppSettings('enable_queue_jobs_for_campaigns')" :label="__tr('Enabled - ')" /> <span x-show="enable_queue_jobs_for_campaigns">{{  __tr('Queue Job/Worker') }}</span> <span x-show="!enable_queue_jobs_for_campaigns">{{  __tr('Cron Job') }}</span>
                    </label>
                </div>
                <fieldset x-show="enable_queue_jobs_for_campaigns" class="">
                    <legend>{{  __tr('Queue Job Setup Instructions') }}</legend>
                   <div>
                    {{  __tr('You need to configure your queue driver as required in .env file. Database tables for the database driver already provided no need to migrate to create tables.') }} <a target="_blank" href="https://laravel.com/docs/10.x/queues">{{  __tr('Laravel Queues') }} <i class="fa fa-external-link-alt"></i></a>
                   </div>
                  <div>
                    {{  __tr('You need to run queue worker as suggested') }} <a target="_blank" href="https://laravel.com/docs/10.x/queues#running-the-queue-worker">{{  __tr('Running Queue Worker') }} <i class="fa fa-external-link-alt"></i></a>
                    <div class="alert alert-dark my-3 col-sm-12 col-md-6 col-lg-4">
                        php artisan queue:work
                    </div>
                    @if (getAppSettings('queue_setup_using_artisan_at'))
                        <h3 class="text-success my-4">
                            <i class="fa fa-check"></i> {{  __tr('As \'artisan queue:work\' command executed, system assumes that queue worker setup is done.') }}
                        </h3>
                    @endif
                  </div>
                  <div class="">
                    @if (getAppSettings('queue_setup_done_at'))
                    <h2 class="text-success my-4">
                        <i class="fa fa-check"></i> {{  __tr('You have confirmed that queue worker setup has been done at __doneAt__', [
                            '__doneAt__' => formatDateTime(getAppSettings('queue_setup_done_at'))
                        ]) }}
                    </h2>
                    @else
                    <a class="btn btn-primary lw-btn-block-mobile lw-ajax-link-action" data-method="post" x-bind:data-post-data="toJsonString({'queue_setup_done_at':'{{  now() }}'})" href="<?= route('manage.configuration.write', ['pageType' => 'internals']) ?>"> <?= __tr('Queue Worker Setup: Mark as Done') ?></a>
                    @endif
                </div>
                </fieldset>
            {{-- CronJob --}}
            <fieldset x-show="!enable_queue_jobs_for_campaigns" x-data="{panelOpened:false}" x-cloak>
                <legend @click="panelOpened = !panelOpened">{{ __tr('Cron Job Setup') }} <small class="text-muted">{{  __tr('Click to expand/collapse') }}</small></legend>
                <div x-show="panelOpened">
                    <p>{{  __tr('You need to setup cron as given below for every minute') }}</p>
                    <div class="col-sm-12 col-md-10">
                        <fieldset>
                            <legend>{{  __tr('Recommended') }}</legend>
                            @if (defined('PHP_BINDIR') and PHP_BINDIR)
                        <p>{{  __tr('You need to add a single cron configuration entry to your server that runs the schedule:run command every minute.') }}</p>
                        <div class="input-group">
                            <input type="text" class="form-control" readonly id="lwArtisanPHPBinaryOption" value="{{ isDemo() ? __tr('/path-to-the-php-binary') : PHP_BINDIR }}/php {{ isDemo() ? __tr('/path-to-your-project') : base_path() }}/artisan schedule:run >> /dev/null 2>&1">
                            <div class="input-group-append">
                                <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwArtisanPHPBinaryOption')">
                                    <?= __tr('Copy') ?>
                                </button>
                            </div>
                        </div>
                        <h3 class="my-4 w-100 text-center text-muted">{{  __tr('------- OR -------') }}</h3>
                        <div class="text-orange my-2">{{  __tr('If special characters not accepting you can use this') }}</div>
                        <div class="input-group">
                            <input type="text" class="form-control" readonly id="lwArtisanPHPBinaryOption2" value="{{ isDemo() ? __tr('/path-to-the-php-binary') : PHP_BINDIR }}/php {{ isDemo() ? __tr('/path-to-your-project') : base_path() }}/artisan schedule:run">
                            <div class="input-group-append">
                                <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwArtisanPHPBinaryOption2')">
                                    <?= __tr('Copy') ?>
                                </button>
                            </div>
                        </div>
                        <h3 class="my-4 w-100 text-center text-muted">{{  __tr('------- OR -------') }}</h3>
                        @endif
                        <div class="input-group">
                            <input type="text" class="form-control" readonly id="lwArtisanOption" value="php {{ isDemo() ? __tr('/path-to-your-project') : base_path() }}/artisan schedule:run >> /dev/null 2>&1">
                            <div class="input-group-append">
                                <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwArtisanOption')">
                                    <?= __tr('Copy') ?>
                                </button>
                            </div>
                        </div>
                        <h3 class="my-4 w-100 text-center text-muted">{{  __tr('------- OR -------') }}</h3>
                        <div class="text-orange my-2">{{  __tr('If special characters not accepting you can use this') }}</div>
                        <div class="input-group">
                            <input type="text" class="form-control" readonly id="lwArtisanOption2" value="php {{ isDemo() ? __tr('/path-to-your-project') : base_path() }}/artisan schedule:run">
                            <div class="input-group-append">
                                <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwArtisanOption2')">
                                    <?= __tr('Copy') ?>
                                </button>
                            </div>
                        </div>
                        @if (getAppSettings('cron_setup_using_artisan_at'))
                        <h3 class="text-success my-4">
                            <i class="fa fa-check"></i> {{  __tr('As \'artisan schedule:run\' command executed, system assumes that cron setup is done.') }}
                        </h3>
                        @else
                        <h3 class="text-orange my-4">
                            {{ __tr('System does not recognized that cron job has been setup using artisan schedule:run schedule command. This is just for information you can use other ways as stated on this page.')  }}
                        </h3>
                        @endif
                        </fieldset>
                        <h3 class="my-4 w-100 text-center text-muted">{{  __tr('------- OR -------') }}</h3>
                        <div class="input-group">
                            <input type="text" class="form-control" readonly id="lwWgetOption" value='wget -O - -q "{{ route('campaign.run_schedule.process') }}" --user-agent="cron" > /dev/null 2>&1'>
                            <div class="input-group-append">
                                <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwWgetOption')">
                                    <?= __tr('Copy') ?>
                                </button>
                            </div>
                        </div>
                        <h3 class="my-4 w-100 text-center text-muted">{{  __tr('------- OR -------') }}</h3>
                        <p>{{  __tr('Or you can find other ways on net to run cron job you need to access following url for the same.') }}</p>
                        <div class="input-group">
                            <input type="text" class="form-control" readonly id="lwCommonUrlOption" value='{{ route('campaign.run_schedule.process') }}'>
                            <div class="input-group-append">
                                <button class="btn btn-outline-light" type="button" onclick="lwCopyToClipboard('lwCommonUrlOption')">
                                    <?= __tr('Copy') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        @if (getAppSettings('cron_setup_done_at'))
                        <h2 class="text-success my-4">
                            <i class="fa fa-check"></i> {{  __tr('You have confirmed that cron setup has been done at __doneAt__', [
                                '__doneAt__' => formatDateTime(getAppSettings('cron_setup_done_at'))
                            ]) }}
                        </h2>
                    @else
                        <a class="btn btn-primary lw-btn-block-mobile lw-ajax-link-action" data-method="post" x-bind:data-post-data="toJsonString({'cron_setup_done_at':'{{  now() }}'})" href="<?= route('manage.configuration.write', ['pageType' => 'internals']) ?>"> <?= __tr('Cron Setup: Mark as Done') ?></a>
                        @endif
                    </div>
                </div>
            </fieldset>
            </fieldset>

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

            <div class="form-group col">
                <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
            </div>
        </form>
    </fieldset>
    
    <fieldset x-data="{panelOpened:false,broadcastConnectionDriver:'{{ getAppSettings('broadcast_connection_driver') }}'}" x-cloak>
        <legend @click="panelOpened = !panelOpened">{{ __tr('Realtime Communication Provider') }} <span class="text-danger my-2">{{ __tr('* required for realtime updates') }}</span> <small class="text-muted">{{  __tr('Click to expand/collapse') }}</small></legend>
       <div x-show="panelOpened">
        <form class="lw-ajax-form lw-form" method="post" action="<?= route('manage.configuration.write', ['pageType' => 'pusher']) ?>" x-cloak x-data="{pusherSettingsExists: {{ getAppSettings('pusher_app_id') ? 1 : 0 }}}">
            <div x-show="pusherSettingsExists"></div>
             <div class="form-group" x-cloak x-show="pusherSettingsExists">
               <div class="btn-group">
                   <button type="button" disabled="true" class="btn btn-success lw-btn">
                       {{ __tr('Broadcast Connection Driver Settings are exist') }}
                   </button>
                   <button type="button" @click="pusherSettingsExists = !pusherSettingsExists"
                       class="btn btn-light lw-btn">{{ __tr('Update') }}</button>
               </div>
           </div>
           <div x-show="!pusherSettingsExists" class="col-sm-12 col-xl-8 col-lg-12">

            <div>
                <h3>{{  __tr('Broadcast Driver') }}</h3>
                <input type="radio" x-model="broadcastConnectionDriver" name="broadcast_connection_driver" value="pusher" id="pusherAsBroadcastDriver"> <label class="mr-2" for="pusherAsBroadcastDriver">{{  __tr('Pusher') }}</label>
                <input type="radio" x-model="broadcastConnectionDriver" name="broadcast_connection_driver" value="soketi" id="soketiAsBroadcastDriver"> <label class="mr-2" for="soketiAsBroadcastDriver">{{  __tr('Soketi') }}</label>
            </div>
            <div class="alert alert-dark" x-show="broadcastConnectionDriver == 'pusher'">
                {{  __tr('You need to create Channel app at pusher.com just name the app and select cluster, once created just go to get app keys.') }} <a href="https://pusher.com" class="float-right" target="_blank">{{  __tr('Got to pusher.com') }}</a>
            </div>
            <div class="alert alert-dark" x-show="broadcastConnectionDriver == 'soketi'">
                {{  __tr('For more information visit:') }} <a href="https://docs.soketi.app" class="float-right" target="_blank">{{  __tr('More information about Soketi') }}</a>
            </div>
               <x-lw.input-field type="text" id="lwPusherAppId" data-form-group-class="" :label="__tr('App ID')" name="pusher_app_id" required="true" />
               <x-lw.input-field type="text" id="lwPusherKey" data-form-group-class="" :label="__tr('App Key')" name="pusher_app_key" required="true" />
               <x-lw.input-field type="text" id="lwPusherAppSecret" data-form-group-class="" :label="__tr('App Secret')" name="pusher_app_secret" required="true" />
               <template x-if="broadcastConnectionDriver == 'pusher'">
                <x-lw.input-field type="text" id="lwPusherAppCluster" data-form-group-class="" :label="__tr('App Cluster')" name="pusher_app_cluster" required="true" />
               </template>
               {{-- soketi specific --}}
               <template x-if="broadcastConnectionDriver == 'soketi'">
                <div>
                    <x-lw.input-field type="text" id="lwPusherAppHost" data-form-group-class="" value="{{ getAppSettings('pusher_app_host') }}" :label="__tr('App Host')" name="pusher_app_host" required="true" />
                    <x-lw.input-field type="number" min="0" id="lwPusherAppPort" data-form-group-class="" value="{{ getAppSettings('pusher_app_port') }}" :label="__tr('App Port')" name="pusher_app_port" required="true" />
                    <x-lw.input-field type="text" id="lwPusherAppScheme" data-form-group-class="" value="{{ getAppSettings('pusher_app_scheme') }}" :label="__tr('App Scheme')" name="pusher_app_scheme" required="true" />
                    <div class="form-group">
                        <x-lw.checkbox id="lwPusherAppUseTls" name="pusher_app_use_tls" :offValue="0" :checked="getAppSettings('pusher_app_use_tls')" :label="__tr('Use TLS')" />
                    </div>
                    <div class="form-group">
                        <x-lw.checkbox id="lwPusherAppEncrypted" name="pusher_app_encrypted" :offValue="0" :checked="getAppSettings('pusher_app_encrypted')" :label="__tr('Encrypted')" />
                    </div>
                </div>
               </template>
           <div class="form-group">
               {{-- submit button --}}
           <button type="submit" href class="btn btn-primary btn-user lw-btn-block-mobile">
               <?= __tr('Save') ?>
           </button>
           </div>
           </div>
        </form>
       </div>
    </fieldset>
        <fieldset x-data="{panelOpened:false}" x-cloak>
            <legend @click="panelOpened = !panelOpened">{{ __tr('Microsoft Translator API') }} <small class="text-muted">{{  __tr('Click to expand/collapse') }}</small></legend>
            <form x-show="panelOpened" class="lw-ajax-form lw-form" method="post" action="<?= route('manage.configuration.write', ['pageType' => 'integrations']) ?>" x-data="{disableMicrosoftTranslatorKeyUpdate:'{{ getAppSettings("microsoft_translator_api_key")}}'}">
                <div class="alert alert-light ">
                  <a target="_blank" class="text-white"
                    href="https://azure.microsoft.com/en-us/pricing/details/cognitive-services/translator-text-api/">https://azure.microsoft.com/en-us/pricing/details/cognitive-services/translator-text-api/</a>
                 </div>
                <div class="form-group " >
                <div x-cloak x-show="!disableMicrosoftTranslatorKeyUpdate">
                       <!--microsift translator -->
                <label for="lwMicrosoftTranslatorAPIKey">
                    <?= __tr('Microsoft Translator API Key') ?>
                </label>
                <input type="text" class="form-control form-control-user" name="microsoft_translator_api_key"
                    id="lwMicrosoftTranslatorAPIKey">
                    <div class="form-group">
                        <!-- /microsift translator -->

                </div>
                  <!--microsift translator api Region -->
                <div x-cloak x-show="!disableMicrosoftTranslatorKeyUpdate">
                    <label for="lwMicrosoftTranslatorAPIRegionKey">
                        <?= __tr('Region') ?>
                    </label>
                    <input type="text" class="form-control form-control-user" name="microsoft_translator_api_region"
                        id="lwMicrosoftTranslatorAPIRegionKey">
                        <div class="form-group">
                </div>
                  <!--/microsift translator api Region -->
                    <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
                    </div>
                </div>
                <div class="form-group" x-cloak x-show="disableMicrosoftTranslatorKeyUpdate">
                <div class="btn-group" id="lwLiveStripeCheckoutExists">
                    <button type="button" disabled="true" class="btn btn-success lw-btn">
                        {{ __tr('Microsoft Translator API Key exist') }}
                    </button>
                    <button type="button"
                        @click="disableMicrosoftTranslatorKeyUpdate = !disableMicrosoftTranslatorKeyUpdate"
                        class="btn btn-light lw-btn">{{ __tr('Update Key') }}</button>
                </div>
                </div>
            </form>
        </fieldset>
        <!-- Start recaptcha settings -->
        <fieldset x-data="{panelOpened:false}" x-cloak>
            <legend @click="panelOpened = !panelOpened">{{ __tr("Google Re-Captcha") }} <small class="text-muted">{{  __tr('Click to expand/collapse') }}</small></legend>
            <form x-show="panelOpened" class="lw-ajax-form lw-form" method="post"
            action="<?= route('manage.configuration.write', ['pageType' => 'integrations']) ?>">
            <div class="form-group">
                <!-- Recaptcha login settings -->
                <!-- Enable Recaptcha login hidden field -->
                <input type="hidden" name="enable_recaptcha" value="0" />
                <!-- /Enable Recaptcha login hidden field -->
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" onclick="allowFunction()" id="lwAllowRecaptcha"
                        name="enable_recaptcha" value="1" <?=getAppSettings('enable_recaptcha')==true ? 'checked' : ''
                        ?>>
                        <label class="custom-control-label" for="lwAllowRecaptcha">
                            {{ __tr('Enable ReCaptcha') }}
                         </label>
                </div>
                <!-- /allow Recaptcha login input radio field -->
                <div class="mt-3" id="inputFieldShow" style="display:none">
                    <!-- Show after Recaptcha login allow information -->

                    <div class="btn-group mx-4" id="lwIsReCaptchaKeysExist">
                        <button type="button" disabled="true" class="btn btn-success lw-btn lw-payment-mbl-view">
                            <?= __tr('ReCaptcha keys are installed.') ?>
                        </button>
                        <button type="button" class="btn btn-light lw-btn" id="lwAddReCaptchaKeys">
                            <?= __tr('Update') ?>
                        </button>
                    </div>
                    <!-- Show after Recaptcha login allow information -->

                    <!-- Recaptcha key exists hidden field -->
                    <input type="hidden" name="reacptcha_keys_exist" id="lwRecaptchaKeysExist" value="" />
                    <!-- Recaptcha key exists hidden field -->
                    <!-- Enable/Disable check box -->
                    <div id="lwReCaptchaLoginInputField" class="px-1">
                        <!-- /Recaptcha Client Secret -->
                        <!--Recaptcha Client Secret -->
                        <div class="mx-4">
                            <label for="lwReCaptchaClientSecret">
                                <?= __tr('Site Key') ?>
                            </label>
                            <input type="text" class="form-control form-control-user" name="recaptcha_site_key"
                                placeholder="<?= __tr('Recaptcha Site Key') ?>" id="lwReCaptchaClientSecret">
                        </div>
                        <div class="mx-4">
                            <label for="lwSecretKey">
                                <?= __tr('Secret Key') ?>
                            </label>
                            <input type="text" class="form-control form-control-user" name="recaptcha_secret_key"
                                id="lwSecretKey" placeholder="<?= __tr('Recaptcha Secret Key') ?>">
                        </div>
                    </div>
                </div>
                <!-- Enable/Disable check box -->
                <div class="form-group">
                    <!-- /recaptcha -->
                <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
                </div>
                <!-- / Recaptcha login settings -->
            </div>
        </form>
        </fieldset>
    <fieldset x-data="{panelOpened:false}" x-cloak>
        <legend @click="panelOpened = !panelOpened">{{ __tr('API Documentation URL for vendors') }} <small class="text-muted">{{  __tr('Click to expand/collapse') }}</small></legend>
        <form x-show="panelOpened" class="lw-ajax-form lw-form" method="post" action="<?= route('manage.configuration.write', ['pageType' => 'integrations']) ?>">
            <div class="alert alert-light">
                {{  __tr('Default API Documentation URL') }}
                <div><a class="text-white" target="_blank" href="https://documenter.getpostman.com/view/17404097/2sA35D4hpx">https://documenter.getpostman.com/view/17404097/2sA35D4hpx</a></div>
            </div>
            <x-lw.input-field type="text" id="lwApiDocumentationUrl" data-form-group-class="" value="{{ getAppSettings('api_documentation_url') }}" :label="__tr('API Documentation URL')" name="api_documentation_url" required="true" />
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
            </div>
        </form>
    </fieldset>
    <fieldset x-data="{panelOpened:false}" x-cloak>
        <legend @click="panelOpened = !panelOpened">{{ __tr('Footer Code (Google Analytics etc)') }} <small class="text-muted">{{  __tr('Click to expand/collapse') }}</small></legend>
        <form  x-show="panelOpened" class="lw-ajax-form lw-form" method="post" action="<?= route('manage.configuration.write', ['pageType' => 'integrations']) ?>">
        <div class="alert alert-light">
            {{ __tr('Use script tag as required. You can use this place for various codes like Google Analytics etc') }}
        </div>
        <div class="mb-3 mb-sm-0">
            <label id="lwFooterCode">{{  __tr('For all the users') }} </label>
            <textarea rows="10" id="lwFooterCode" class="lw-form-field form-control" placeholder="{{ __tr('You can add your required js code etc here using script tags it will executed all the user pages') }}" name="page_footer_code_all">{!! getAppSettings('page_footer_code_all') !!}</textarea>
        </div>
        <div class="my-4 mb-sm-0">
            <label id="lwFooterCodeLoggedIn">{{  __tr('Restricted to logged in users') }} </label>
            <textarea rows="10" id="lwFooterCodeLoggedIn" class="lw-form-field form-control" placeholder="{{ __tr('You can add your required js code etc here using script tags it will only executed on logged in user pages') }}"  name="page_footer_code_logged_user_only">{!! getAppSettings('page_footer_code_logged_user_only') !!}</textarea>
        </div>
        <div class="form-group" name="footer_code">
            <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
        </div>
    </form>
    </fieldset>

</section>


@push('appScripts')
<script>
    (function($) {
        'use strict';
        
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
<script>
    "use strict";
        function allowFunction() {
        var checkBox = document.getElementById("lwAllowRecaptcha");
        var text = document.getElementById("inputFieldShow");
        if (checkBox.checked == true) {
            text.style.display = "block";
        } else {
            text.style.display = "none";
        }
    }


    //recaptcha login js block start
    $(document).ready(function() {
        var allowReCaptchaLogin = $("#lwAllowRecaptcha").is(':checked');
        if (allowReCaptchaLogin) {
            $("#inputFieldShow").show()
        }
        //is true then disable input field
        if (!allowReCaptchaLogin) {
            $("#lwReCaptchaLoginInputField").addClass('lw-disabled-block-content');
            $('#lwAddReCaptchaKeys').attr("disabled", true);
        }

        //allow Recaptcha switch on change event
        $("#lwAllowRecaptcha").on('change', function(e) {
            allowReCaptchaLogin = $(this).is(":checked");

            //if condition false then add class
            if (!allowReCaptchaLogin) {
                $("#lwReCaptchaLoginInputField").addClass('lw-disabled-block-content');
                $('#lwAddReCaptchaKeys').attr("disabled", true);
            } else {
                $("#lwReCaptchaLoginInputField").removeClass('lw-disabled-block-content');
                $('#lwAddReCaptchaKeys').attr("disabled", false);
            }
        });

        /*********** Recaptcha Keys setting start here ***********/
        var isReCaptchaKeysInstalled = "<?= getAppSettings('enable_recaptcha') ?>",
          lwReCaptchaLoginInputField = $('#lwReCaptchaLoginInputField'),
          lwIsReCaptchaKeysExist = $('#lwIsReCaptchaKeysExist');

        // Check if test Recaptcha login keys are installed
        if (isReCaptchaKeysInstalled) {
            lwReCaptchaLoginInputField.hide();
            lwIsReCaptchaKeysExist.show();
        } else {
            lwIsReCaptchaKeysExist.hide();
        }
        // Update Recaptcha login checkout testing keys
        $('#lwAddReCaptchaKeys').click(function() {
            $("#lwRecaptchaKeysExist").val(0);
            lwReCaptchaLoginInputField.show();
            lwIsReCaptchaKeysExist.hide();
        });
        /*********** Recaptcha Keys setting end here ***********/
    });
    //Recaptcha login js block end

    //on integration setting success callback function
    function onIntegrationSettingCallback(responseData) {
        //check reaction code is 1 then reload view
        if (responseData.reaction == 1) {
            showConfirmation("{{ __tr('Settings Updated Successfully') }}", function() {
                __Utils.viewReload();
            }, {
                confirmButtonText: "{{ __tr('Reload Page') }}",
                type: "success"
            });
        }
    };
</script>
@endpush