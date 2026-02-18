<nav class="navbar navbar-top navbar-horizontal navbar-expand-md shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="omx-logo" href="{{ route('landing_page') }}" style="flex: 0 0 auto;">
            @if (isVendorShop())
                <img src="{{ getVendorSettings('logo_image_url') }}" class="navbar-brand-img " style="max-width:60% !important; "
                    alt="{{ getVendorSettings('name') }}" >
            @else
                <img src="{{ getAppSettings('logo_image_url') }}" class="navbar-brand-img" style="max-width:60% !important;"
                    alt="{{ getAppSettings('name') }}">
            @endif
        </a>
        
        <!-- Mobile Context Menu Button (Only visible on mobile) -->
        <div class="dropdown d-block d-md-none" style="flex: 0 0 auto; margin-left: auto; position: relative; z-index: 1050;">
            <button class="btn dropdown-toggle" type="button" id="contactContextMenuButton"
                data-bs-toggle="dropdown" data-toggle="dropdown" aria-expanded="false" aria-haspopup="true"
                style="background: white; color: #000; padding: 10px 14px; border-radius: 5px; min-width: 50px;  cursor: pointer; pointer-events: auto;">
                <i class="fas fa-bars" style="font-size: 2rem; line-height: 1; display: inline-block;"></i>
            </button>
            <style>
                #contactContextMenuButton::after {
                    display: none !important;
                }
            </style>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="contactContextMenuButton" style="min-width: 200px; max-width: 90vw; padding: 10px; border-radius: 10px; z-index: 1051; position: absolute; right: 0; left: auto; margin-right: 0.5rem; margin-top: 0.5rem;">
                <!-- Menu -->
                @if (!getAppSettings('other_home_page_url'))
                <li><a class="dropdown-item" href="{{ url('/#features') }}">{{ __tr('Features') }}</a></li>
                <li><a class="dropdown-item" href="{{ url('/#pricing') }}">{{ __tr('Pricing') }}</a></li>
                @endif
                <li><a class="dropdown-item" href="{{ route('user.contact.form') }}">{{ __tr('Contact') }}</a></li>
                
                <!-- /pages -->
                @php
                    $pageData = getActivePages();
                @endphp
                @if(!__isEmpty(getActivePages()))
                    @foreach($pageData as $pageKey => $pageValue)
                    <li><a class="dropdown-item" href="{{ route('page.preview', [
                        'pageUId' => $pageValue['_uid'],
                        'slug' => slugIt($pageValue['slug']),
                    ])}}">{{ __tr($pageValue['title']) }}</a></li>
                    @endforeach
                @endif
                <!-- /pages -->
                
                @if (!isLoggedIn())
                <li><hr class="dropdown-divider"></li>
                @if(getAppSettings('enable_vendor_registration') or getAppSettings('message_for_disabled_registration'))
                <li><a class="dropdown-item text-success fw-bold" href="{{ route('auth.register') }}">{{ __tr('Register') }}</a></li>
                @endif
                <li><a class="dropdown-item text-white fw-bold" style="background: linear-gradient(15deg, rgb(0, 136, 68), rgb(32, 170, 129), rgb(13, 90, 67));" href="{{ route('auth.login') }}">{{ __tr('Login') }}</a></li>
                @endif
                @if (isLoggedIn())
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-white fw-bold" style="background-color: #198754;" href="{{ route('central.console') }}">{{ __tr('Dashboard') }}</a></li>
                @endif
                @php
                    $translationLanguages = getActiveTranslationLanguages();
                    $configCurrentLocale = app()->getLocale();
                @endphp
                @if (!__isEmpty($translationLanguages) and (count($translationLanguages) > 1))
                <li><hr class="dropdown-divider"></li>
                <li class="dropdown-header">{{ __tr('Choose your language') }}</li>
                @foreach($translationLanguages as $languageId => $language)
                    @if(($languageId != $configCurrentLocale) and (!isset($language['status']) or $language['status'] != false))
                    <li><a class="dropdown-item lw-ajax-link-action" data-show-processing="true" href="{{ route('locale.change', ['localeID' => $languageId]) }}">
                        <i class="fas fa-language me-2"></i>{{ $language['name'] }}
                    </a></li>
                    @endif
                @endforeach
                @endif
                <!-- /Menu -->
            </ul>
        </div>
        <!-- /Mobile Context Menu Button -->
        
        <!-- Desktop/Tablet Navigation (Visible on tablet and laptop) -->
        <div class="collapse navbar-collapse d-none d-md-flex" id="navbar-collapse-main" style="flex: 1 1 auto; width: auto;">
            <!-- Navbar items -->
            <ul class="navbar-nav ml-auto">
                @if (!getAppSettings('other_home_page_url'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ url('/#features') }}">
                        <span class="nav-link-inner--text">{{ __tr('Features') }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ url('/#pricing') }}">
                        <span class="nav-link-inner--text">{{ __tr('Pricing') }}</span>
                    </a>
                </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('user.contact.form') }}">{{
                    __tr('Contact') }}</a>
                </li>
                  <!-- pages -->
                  <li class="nav-item">
                    @include('layouts.navbars.navs.pages-menu-partial')
                 </li>
                   <!-- /pages -->
                @if(getAppSettings('enable_vendor_registration') or getAppSettings('message_for_disabled_registration'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('auth.register') }}">
                        <span class="nav-link-inner--text text-danger fw-bold">{{ __tr('Register') }}</span>
                    </a>
                </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('auth.login') }}">
                        <span class="nav-link-inner--text">{{ __tr('Login') }}</span>
                    </a>
                </li>
                @include('layouts.navbars.locale-menu')
            </ul>
        </div>
    </div>
</nav>
<style>
   .navbar-brand {
       font-weight: 300 !important;
       color: #6c757d !important;
       opacity: 0.85;
       transition: opacity 0.3s ease;
   }
   .navbar-brand:hover {
       opacity: 1;
       color: #495057 !important;
   }
   .navbar-brand-img {
       filter: grayscale(20%) brightness(0.9);
       opacity: 0.8;
       transition: all 0.3s ease;
       max-width: 100%;
       height: auto;
   }
   .navbar-brand:hover .navbar-brand-img {
       filter: grayscale(10%) brightness(1);
       opacity: 0.95;
   }
   
   /* Remove padding-right from navbar container */
   .navbar > .container {
       padding-right: 0 !important;
       overflow: visible !important;
   }
   .navbar {
       overflow: visible !important;
   }
   
   /* Responsive Header Styles */
   .navbar .d-flex {
       min-width: 0;
       position: relative;
   }
   .navbar-brand {
       flex-shrink: 1;
       min-width: 0;
       max-width: calc(100% - 60px);
       overflow: hidden;
       padding-right: 0.25rem;
   }
   .dropdown {
       flex-shrink: 0 !important;
       flex-grow: 0 !important;
       position: relative;
   }
   .dropdown-menu {
       max-width: 90vw !important;
       word-wrap: break-word;
       white-space: normal;
   }
   .dropdown-item {
       white-space: normal !important;
       word-wrap: break-word;
   }
   #contactContextMenuButton {
       white-space: nowrap;
       overflow: visible;
       flex-shrink: 0 !important;
       position: relative;
       z-index: 1050 !important;
       display: inline-block !important;
       cursor: pointer !important;
       pointer-events: auto !important;
       transition: none !important;
       animation: none !important;
       border: none !important;
       outline: none !important;
   }
   #contactContextMenuButton:hover,
   #contactContextMenuButton:focus,
   #contactContextMenuButton:active {
       background: white !important;
       color: #000 !important;
       border: none !important;
       border-color: transparent !important;
       box-shadow: none !important;
       transform: none !important;
       transition: none !important;
       animation: none !important;
       outline: none !important;
   }
   #contactContextMenuButton:hover i,
   #contactContextMenuButton:focus i,
   #contactContextMenuButton:active i {
       color: #000 !important;
       transform: none !important;
       transition: none !important;
       animation: none !important;
   }
   #contactContextMenuButton i {
       font-size: 2rem !important;
       line-height: 1;
       display: inline-block !important;
       visibility: visible !important;
       opacity: 1 !important;
       transition: none !important;
       animation: none !important;
       transform: none !important;
   }
   #contactContextMenuButton i.fa-bars:before {
       content: "\f0c9";
       font-family: "Font Awesome 5 Free";
       font-weight: 900;
   }
   
   /* Force mobile menu button to show on mobile */
   @media (max-width: 767.98px) {
       .dropdown.d-block.d-md-none {
           display: block !important;
           visibility: visible !important;
       }
       #contactContextMenuButton {
           display: inline-block !important;
           visibility: visible !important;
       }
   }
   
   @media (max-width: 991.98px) {
       .navbar {
           padding: 0.3rem 1rem;
       }
       .navbar-brand {
           padding: 0.15rem 0;
           max-width: calc(100% - 55px);
       }
       .navbar-brand-img {
           max-height: 35px;
           width: auto;
       }
       #contactContextMenuButton {
           padding: 8px 12px !important;
           font-size: 0.9rem;
           min-width: 50px !important;
       }
       #contactContextMenuButton i {
           font-size: 1.8rem !important;
           line-height: 1;
           display: inline-block !important;
           visibility: visible !important;
       }
       .dropdown-menu {
           min-width: 200px !important;
           max-width: 90vw !important;
           margin-top: 0.5rem !important;
           right: 0 !important;
           left: auto !important;
           margin-right: 0.5rem !important;
       }
   }
   
   @media (max-width: 767.98px) {
       .navbar {
           padding: 0.3rem 0.5rem;
       }
       .navbar-brand {
           max-width: calc(100% - 50px);
           padding: 0.1rem 0;
       }
       .navbar-brand-img {
           max-height: 30px;
       }
       #contactContextMenuButton {
           padding: 6px 10px !important;
           font-size: 0.85rem;
           min-width: 48px !important;
       }
       #contactContextMenuButton i {
           font-size: 1.6rem !important;
           line-height: 1;
           display: inline-block !important;
           visibility: visible !important;
       }
       .container-fluid {
           padding-left: 0.5rem !important;
           padding-right: 0.5rem !important;
       }
   }
   
   @media (max-width: 575.98px) {
       .navbar {
           padding: 0.3rem 0.25rem;
       }
       .navbar-brand {
           max-width: calc(100% - 45px);
           padding: 0.1rem 0;
       }
       .navbar-brand-img {
           max-height: 28px;
       }
       #contactContextMenuButton {
           padding: 6px 8px !important;
           min-width: 45px !important;
       }
       #contactContextMenuButton i {
           font-size: 1.4rem !important;
           line-height: 1;
           display: inline-block !important;
           visibility: visible !important;
       }
       .dropdown-menu {
           min-width: 180px !important;
           max-width: 85vw !important;
           font-size: 0.9rem;
           right: 0 !important;
           left: auto !important;
           margin-right: 0.25rem !important;
       }
       .container-fluid {
           padding-left: 0.25rem !important;
           padding-right: 0.25rem !important;
       }
   }
   
   @media (max-width: 420px) {
       .navbar {
           padding: 0.25rem 0.15rem !important;
       }
       .navbar-brand {
           max-width: calc(100% - 42px) !important;
           padding: 0.1rem 0 !important;
       }
       .navbar-brand-img {
           max-height: 24px !important;
           max-width: 100% !important;
       }
       #contactContextMenuButton {
           padding: 4px 6px !important;
           min-width: 36px !important;
       }
       #contactContextMenuButton i {
           font-size: 0.9rem !important;
       }
       .container-fluid {
           padding-left: 0.15rem !important;
           padding-right: 0.15rem !important;
       }
       .navbar .d-flex {
           gap: 0.25rem !important;
       }
   }
   
   @media (max-width: 360px) {
       .navbar {
           padding: 0.25rem 0.1rem !important;
       }
       .navbar-brand {
           max-width: calc(100% - 38px) !important;
           padding: 0.1rem 0 !important;
       }
       .navbar-brand-img {
           max-height: 22px !important;
       }
       #contactContextMenuButton {
           padding: 3px 5px !important;
           min-width: 34px !important;
       }
       #contactContextMenuButton i {
           font-size: 0.85rem !important;
       }
       .container-fluid {
           padding-left: 0.1rem !important;
           padding-right: 0.1rem !important;
       }
   }
   
   /* Ensure mobile menu button is visible on mobile */
   @media (max-width: 767.98px) {
       .dropdown.d-block.d-md-none {
           display: block !important;
       }
   }
   
   @media (min-width: 768px) {
       .navbar-brand-img {
           max-height: 45px;
       }
       .navbar-nav {
           display: flex;
           align-items: center;
       }
       .navbar-nav .nav-item {
           margin: 0 0.25rem;
       }
       .dropdown.d-block.d-md-none {
           display: none !important;
       }
       .navbar-collapse {
           display: flex !important;
           flex-basis: auto !important;
           width: auto !important;
       }
   }
   
   /* Button gradient styles matching main page */
   .btnn {
       background: linear-gradient(
           15deg,
           rgb(0, 136, 68),
           rgb(32, 170, 129),
           rgb(13, 90, 67),
           rgb(26, 160, 131),
           rgb(8, 136, 97),
           rgb(17, 119, 68),
           rgb(63, 204, 169),
           rgb(9, 95, 62),
           rgb(0, 136, 95)
       ) no-repeat;
       background-size: 300%;
       background-position: left center;
       transition: background 1s ease;
       color: white !important;
       animation: gradientMove 4s linear infinite;
   }
   @keyframes gradientMove {
       0% {
           background-position: right center;
       }
       100% {
           background-position: center left;
       }
   }
</style>