<!-- Page Heading -->
<h1>{{ __tr('Media') }}</h1>
<!-- Page Heading -->
<hr>
<!-- Media setting form -->
<form class="lw-ajax-form lw-form" method="post" action="<?= route('manage.configuration.write', ['pageType' => request()->pageType]) ?>">
    <fieldset>
        <legend>{{ __tr('Sheet Integration Video Link') }}</legend>
        <!-- Sheet Integration Video Link -->
        <div class="form-group">
            <label for="lwSheetIntegrationVideoLink"><?= __tr('Sheet Integration Video Link') ?></label>
            <input type="url" class="form-control form-control-user" name="sheet_integration_video_link" id="lwSheetIntegrationVideoLink" value="<?= $configurationData['sheet_integration_video_link'] ?? '' ?>">
            <small class="help-text">{{ __tr('Enter the video link URL for sheet integration tutorial or documentation') }}</small>
        </div>
        <div class="form-group">
            <x-lw.checkbox id="lwSheetIntegrationShowInUserPanel" name="sheet_integration_show_in_user_panel" :offValue="0" :checked="getAppSettings('sheet_integration_show_in_user_panel')" :label="__tr('Show in User Panel')" />
        </div>
        <!-- /Sheet Integration Video Link -->
        <hr>
        <div class="form-group col">
            <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
        </div>
    </fieldset>

    <fieldset class="mt-4">
        <legend>{{ __tr('Demo Links') }}</legend>
        <!-- Demo Link 1 -->
        <div class="form-group">
            <label for="lwDemoLink1"><?= __tr('Demo Link 1') ?></label>
            <input type="url" class="form-control form-control-user" name="demo_link_1" id="lwDemoLink1" value="<?= $configurationData['demo_link_1'] ?? '' ?>">
            <small class="help-text">{{ __tr('Enter the demo link URL') }}</small>
        </div>
        <div class="form-group">
            <x-lw.checkbox id="lwDemoLink1ShowInUserPanel" name="demo_link_1_show_in_user_panel" :offValue="0" :checked="getAppSettings('demo_link_1_show_in_user_panel')" :label="__tr('Show in User Panel')" />
        </div>
        <!-- /Demo Link 1 -->
        
        <!-- Demo Link 2 -->
        <div class="form-group">
            <label for="lwDemoLink2"><?= __tr('Demo Link 2') ?></label>
            <input type="url" class="form-control form-control-user" name="demo_link_2" id="lwDemoLink2" value="<?= $configurationData['demo_link_2'] ?? '' ?>">
            <small class="help-text">{{ __tr('Enter the demo link URL') }}</small>
        </div>
        <div class="form-group">
            <x-lw.checkbox id="lwDemoLink2ShowInUserPanel" name="demo_link_2_show_in_user_panel" :offValue="0" :checked="getAppSettings('demo_link_2_show_in_user_panel')" :label="__tr('Show in User Panel')" />
        </div>
        <!-- /Demo Link 2 -->
        
        <!-- Demo Link 3 -->
        <div class="form-group">
            <label for="lwDemoLink3"><?= __tr('Demo Link 3') ?></label>
            <input type="url" class="form-control form-control-user" name="demo_link_3" id="lwDemoLink3" value="<?= $configurationData['demo_link_3'] ?? '' ?>">
            <small class="help-text">{{ __tr('Enter the demo link URL') }}</small>
        </div>
        <div class="form-group">
            <x-lw.checkbox id="lwDemoLink3ShowInUserPanel" name="demo_link_3_show_in_user_panel" :offValue="0" :checked="getAppSettings('demo_link_3_show_in_user_panel')" :label="__tr('Show in User Panel')" />
        </div>
        <!-- /Demo Link 3 -->
        
        <!-- Demo Link 4 -->
        <div class="form-group">
            <label for="lwDemoLink4"><?= __tr('Demo Link 4') ?></label>
            <input type="url" class="form-control form-control-user" name="demo_link_4" id="lwDemoLink4" value="<?= $configurationData['demo_link_4'] ?? '' ?>">
            <small class="help-text">{{ __tr('Enter the demo link URL') }}</small>
        </div>
        <div class="form-group">
            <x-lw.checkbox id="lwDemoLink4ShowInUserPanel" name="demo_link_4_show_in_user_panel" :offValue="0" :checked="getAppSettings('demo_link_4_show_in_user_panel')" :label="__tr('Show in User Panel')" />
        </div>
        <!-- /Demo Link 4 -->
        
        <hr>
        <div class="form-group col">
            <button type="submit" class="btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
        </div>
    </fieldset>
</form>

