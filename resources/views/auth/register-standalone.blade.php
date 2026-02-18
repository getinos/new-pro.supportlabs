<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $CURRENT_LOCALE_DIRECTION ?? 'ltr' }}">
<head>
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __tr('Register') }} - {{ getAppSettings('name') }}</title>
    <!-- Favicon -->
    <link href="{{getAppSettings('favicon_image_url') }}" rel="icon">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;1,100;1,200;1,300;1,400;1,500;1,600;1,700&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Nunito+Sans:ital,opsz,wght@0,6..12,200..1000;1,6..12,200..1000&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    {!! __yesset([
        'static-assets/packages/fontawesome/css/all.css',
        'dist/css/common-vendorlibs.css',
        'dist/css/vendorlibs.css',
        'argon/css/argon.min.css',
        'dist/css/app.css',
    ]) !!}
    
    {{-- custom app css --}}
    <link href="{{ route('app.load_custom_style') }}" rel="stylesheet" />
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .gradient-icon-1 {
            background: linear-gradient(135deg, #339699, #78c48f);
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            display: inline-block; 
        }
        .form-container{
            background: rgba(255, 255, 255, 0.95);
            font-family: 'Nunito', sans-serif;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
        }
        .form-container .form-icon{
            font-size: 55px;
            text-align: center;
            line-height: 100px;
            width: 80px;
            height:80px;
            margin: 0 auto 10px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .form-container .title{
            color:rgb(0, 0, 0);
            font-size: 22px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
            margin: 0 0 15px;
        }
        .form-container .form-horizontal .form-group{
             margin: 0 0 15px 0; 
            }
        .form-container .form-horizontal .form-group label{
            font-size: 15px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .form-container .form-horizontal .form-group input{
            width: 100%;
            font-size: 1rem;
            padding: 15px 20px;
            background-color: #f5f5f5;
            color: #333;
            border-radius: 10px;
            outline: none;
            transition: all 0.3s ease;
            border: 2px solid #ddd;
        }
        .form-container .form-horizontal .form-control{
            color: #333;
            background: #ecf0f3;
            font-size: 15px;
            height: 45px;
            padding: 10px 15px;
            letter-spacing: 1px;
            border: none;
            border-radius: 12px;
            display: inline-block;
            transition: all 0.3s ease 0s;
        }
        .form-container .form-horizontal .form-control:focus{
            border-color: #58bc82;
            box-shadow: 0 0 10px rgba(88, 188, 130, 0.4);
        }
        .form-container .form-horizontal .form-control::placeholder{
            color: #808080;
            font-size: 14px;
        }
        .form-container .form-horizontal .btn{
            color: #000;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            width: 100%;
            padding: 10px 15px;
            border-radius: 10px;
            box-shadow: 6px 6px 6px #cbced1, -6px -6px 6px #fff;
            border: none;
            transition: all 0.5s ease 0s;
        }
        .form-container .form-horizontal .btn:hover,
        .form-container .form-horizontal .btn:focus{
            color: #fff;
            letter-spacing: 3px;
            box-shadow: none;
            outline: none;
        }
        .btn{
            color: #000;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            width: 100%;
            padding: 10px 15px;
            border-radius: 10px;
            box-shadow: 6px 6px 6px #cbced1, -6px -6px 6px #fff;
            border: none;
            transition: all 0.5s ease 0s;
        }
        .form-bg{
          margin: 0;
          padding: 0;
          width: 100%;
          min-height: 100vh;
          overflow-y: auto;
          background: linear-gradient(to bottom right, #339699, #297386);
        }
        canvas {
          display: block;
          width: 100%;
          height: 100%;
          position: absolute;
          top: 0;
          left: 0;
        }
        .btn-google, .btn-facebook {
            margin-top: 10px;
        }
        .mb-3.mt-5 {
            margin-top: 20px !important;
        }
        .my-4 {
            margin-top: 10px !important;
            margin-bottom: 10px !important;
        }
        .row {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="form-bg py-5">
    <canvas id="networkCanvas" class="position-absolute"></canvas>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-md-offset-6">
                <div class="form-container">
                    <div class="form-icon"><i class="fas fa-briefcase gradient-icon-1"></i></div>
                    <h3 class="title">Register</h3>
                    <div class="form-horizontal">
                    @if(getAppSettings('enable_vendor_registration'))
                    @php
                    $formSignUpRoute = route('auth.register.process');
                    if (getAppSettings('activation_required_for_new_user')) {
                    $formSignUpRoute = route('activation_required.auth.register.process');
                    }
                    @endphp
                    <x-lw.form :action="$formSignUpRoute" data-secured="true">
                        <div class="form-horizontal">
                        <!-- Vendor Name -->
                        <div class="form-group">
                            <label>Vendor/Company Name</label>
                            <input class="form-control" placeholder="{{ __tr('Vendor/Company Name') }}" type="text"
                                    name="vendor_title" value="{{ old('vendor_title') }}" required autofocus>
                        </div>
                        <!-- Username -->
                        
                        <label style="font-size: 15px; font-weight: 600; text-transform: uppercase;">Name</label>
                        <div class="row">
                            <div class="col-md-6">
                                <!-- First Name -->
                                <div class="form-group">
                                    <input class="form-control" placeholder="{{ __tr('First Name') }}" type="text"
                                        name="first_name" value="{{ old('first_name') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Last Name -->
                                <div class="form-group">
                                    <input class="form-control" placeholder="{{ __tr('Last Name') }}" type="text"
                                            name="last_name" value="{{ old('last_name') }}" required>
                                </div>
                            </div>
                        </div>
                        <!-- mobile no -->
                        <div class="form-group">
                            <label>Mobile Number</label>
                            <input class="form-control" placeholder="{{ __tr('Mobile Number') }}" type="text"
                                    name="mobile_number" value="{{ old('mobile_number') }}" required autofocus>
                            <h5><span>{{__tr("Mobile number should be with country code without 0 or +")}}</span></h5>
                        </div>
                        <!-- /mobile no -->
                        <!-- Email address -->
                        <div class="form-group">
                            <label>email</label>
                            <input class="form-control" placeholder="{{ __tr('Email') }}" type="email" name="email"
                                    value="{{ old('email') }}" required>
                        </div>
                        <!-- Password -->
                        <div class="form-group">
                            <label>password</label>
                            <input class="form-control" placeholder="{{ __tr('Password') }}" type="password"
                                    name="password" required>
                        </div>
                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label>confirm password</label>
                            <input class="form-control" placeholder="{{ __tr('Confirm Password') }}" type="password"
                                    name="password_confirmation" required>
                        </div>
                        <!-- privacy policy -->
                        @if (getAppSettings('user_terms') or getAppSettings('vendor_terms') or getAppSettings('privacy_policy'))
                        <div class="row my-4">
                            <div class="col-12">
                                <div class="custom-control custom-control-alternative custom-checkbox">
                                    <input class="custom-control-input" name="terms_and_conditions" id="itemsAccept"
                                        type="checkbox">
                                    <label class="custom-control-label" for="itemsAccept">
                                        <span class="text-primary">{{ __tr('I agree with the') }}
                                            @if (getAppSettings('user_terms'))
                                            <a class="text-success" href="{{ route('app.terms_and_policies', [
                                                'contentName' => 'user_terms'
                                            ]) }}">{{ __tr('User Terms And Conditions') }}</a>,
                                            @endif
                                            @if (getAppSettings('vendor_terms'))
                                            <a class="text-success" href="{{ route('app.terms_and_policies', [
                                                'contentName' => 'vendor_terms'
                                            ]) }}">{{ __tr('Vendor Terms And Conditions') }}</a>,
                                            @endif
                                            @if (getAppSettings('privacy_policy'))
                                            <a class="text-success" href="{{ route('app.terms_and_policies', [
                                                'contentName' => 'privacy_policy'
                                            ]) }}">{{
                                                __tr('Privacy Policy')
                                                }}</a>
                                            @endif
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        @endif
                        <!-- create account action -->
                        <div class="text-center">
                            <center>
                                <button  type="submit" class="btn btn-success btn-lg btn-block mt-6  mb-5" style="background: linear-gradient(135deg, #41C6B5, #1771E6);"><strong>{{ __tr('Create Account') }}</strong></button>
                            </center>
                        </div>
                    </x-lw.form>
                </div>
                <!-- social login links -->
                @if(getAppSettings('allow_google_login'))
                <a href="<?= route('login.google') ?>" class="btn btn-google btn-user btn-block">
                    <i class="fab fa-google fa-fw"></i> <?= __tr('Continue with Google')  ?>
                </a>
                @endif
                @if(getAppSettings('allow_facebook_login'))
                <a href="<?= route('login.facebook') ?>" class="btn btn-facebook btn-user btn-block">
                    <i class="fab fa-facebook-f fa-fw"></i> <?= __tr('Continue with Facebook')  ?>
                </a>
                @endif
                <!-- social login links -->
                <center>
                    <div class="mb-3 mt-5">
                        {{ __tr('Already have an Account?') }}
                    </div>
                    <a href="{{ route('auth.login') }}" class="btn btn-success my-4 btn-lg btn-block mb-5">
                        <strong>{{ __tr('Click here to login') }}</strong>
                    </a>
                </center>
            </div>
            @else
            <div class="card lw-form-card-box shadow border-0">
                <div class="card-header text-center">
                    @if (getAppSettings('message_for_disabled_registration'))
                    {!! getAppSettings('message_for_disabled_registration') !!}
                @else
                {{ __tr('Vendor Registrations are closed now.') }}
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
    const canvas = document.getElementById('networkCanvas');
    const ctx = canvas.getContext('2d');

    // Set canvas to full window size
    function resizeCanvas() {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    }
    
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    // Particles class with enhanced animation
    class Particle {
      constructor() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.size = Math.random() * 2 + 1;
            this.speedX = (Math.random() - 0.5) * 2;
            this.speedY = (Math.random() - 0.5) * 2;
            this.brightness = Math.random() * 50 + 50;
            // Add wave motion parameters
            this.angle = Math.random() * 360;
            this.angleSpeed = Math.random() * 0.5 + 0.1;
            this.waveAmplitude = Math.random() * 20 + 10;
      }

      draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.closePath();
            // Create a gradient effect for each particle
            const gradient = ctx.createRadialGradient(
                this.x, this.y, 0,
                this.x, this.y, this.size
            );
            gradient.addColorStop(0, `rgba(0, ${this.brightness + 150}, ${this.brightness + 300}, 1)`);
            gradient.addColorStop(1, `rgba(0, ${this.brightness + 150}, ${this.brightness + 300}, 0)`);
            ctx.fillStyle = gradient;
        ctx.fill();
      }

      update() {
            // Wave motion
            this.angle += this.angleSpeed;
            this.x += Math.sin(this.angle * Math.PI / 180) * 0.5;
            this.y += Math.cos(this.angle * Math.PI / 180) * 0.5;

            // Boundary check with smooth transition
            if (this.x < 0) this.x = canvas.width;
            if (this.x > canvas.width) this.x = 0;
            if (this.y < 0) this.y = canvas.height;
            if (this.y > canvas.height) this.y = 0;

            // Pulse size
            this.size = (Math.sin(this.angle * 0.05) + 2) * 1.5;
      }
    }

    // Initialize particles
    const numberOfParticles = 100;
    let particlesArray = [];

    function init() {
      particlesArray = [];
      for (let i = 0; i < numberOfParticles; i++) {
        particlesArray.push(new Particle());
      }
    }

    // Connect particles with enhanced lines
    function connect() {
      for (let a = 0; a < particlesArray.length; a++) {
        for (let b = a; b < particlesArray.length; b++) {
                const dx = particlesArray[a].x - particlesArray[b].x;
                const dy = particlesArray[a].y - particlesArray[b].y;
                const distance = Math.sqrt(dx * dx + dy * dy);

                if (distance < 150) {
                    const opacity = (150 - distance) / 150;
                    const gradient = ctx.createLinearGradient(
                        particlesArray[a].x, particlesArray[a].y,
                        particlesArray[b].x, particlesArray[b].y
                    );
                    gradient.addColorStop(0, `rgba(0, 180, 255, ${opacity * 0.5})`);
                    gradient.addColorStop(1, `rgba(0, 255, 180, ${opacity * 0.5})`);
                    
                    ctx.strokeStyle = gradient;
                    ctx.lineWidth = opacity * 2;
            ctx.beginPath();
            ctx.moveTo(particlesArray[a].x, particlesArray[a].y);
            ctx.lineTo(particlesArray[b].x, particlesArray[b].y);
            ctx.stroke();
          }
        }
      }
    }

    // Animation loop
    function animate() {
      requestAnimationFrame(animate);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      
        particlesArray.forEach(particle => {
            particle.update();
            particle.draw();
        });
      connect();
    }

    init();
    animate();
</script>

<?= __yesset(['dist/js/common-vendorlibs.js','dist/js/vendorlibs.js', 'argon/bootstrap/dist/js/bootstrap.bundle.min.js', 'argon/js/argon.js'], true) ?>
<?= __yesset(
    [
        'dist/js/jsware.js',
        'dist/js/app.js',
        // keep it last
        'dist/js/alpinejs.min.js',
    ],
    true,
) ?>
<script src="{{ route('vendor.load_server_compiled_js') }}"></script>
<script>
    (function($) {
        'use strict';
        window.appConfig = {
            debug: "{{ config('app.debug') }}",
            csrf_token: "{{ csrf_token() }}",
            locale : '{{ app()->getLocale() }}',
        }
    })(jQuery);
</script>
</body>
</html>

