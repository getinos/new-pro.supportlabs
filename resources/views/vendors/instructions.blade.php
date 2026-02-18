@extends('layouts.app', ['title' => __tr('Instructions')])

@section('content')
@include('users.partials.header', [
'title' => __tr('Instructions'),
'description' => __tr('Video tutorials and demo links'),
'class' => 'col-lg-7'
])

<div class="container-fluid mt--7">
    <div class="row">
        <div class="col-xl-12">
            <div class="card shadow">
                <div class="card-header bg-transparent">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 class="mb-0">{{ __tr('Video Instructions') }}</h3>
                            <p class="text-muted mb-0">{{ __tr('Watch tutorials and demo videos to learn how to use the platform') }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        @if(!empty($videoLinks['sheet_integration']))
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="icon icon-shape icon-shape-primary rounded-circle shadow mr-3">
                                            <i class="fas fa-video text-primary" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="mb-0">{{ __tr('Sheet Integration') }}</h5>
                                    </div>
                                    <p class="text-muted mb-3">{{ __tr('Learn how to integrate Google Sheets with the platform') }}</p>
                                    <a href="{{ $videoLinks['sheet_integration'] }}" target="_blank" class="btn btn-primary btn-sm">
                                        <i class="fas fa-play-circle mr-2"></i>{{ __tr('Watch Video') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(!empty($videoLinks['demo_link_1']))
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="icon icon-shape icon-shape-success rounded-circle shadow mr-3">
                                            <i class="fas fa-video text-success" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="mb-0">{{ __tr('Demo Video 1') }}</h5>
                                    </div>
                                    <p class="text-muted mb-3">{{ __tr('Watch this demo video to get started') }}</p>
                                    <a href="{{ $videoLinks['demo_link_1'] }}" target="_blank" class="btn btn-success btn-sm">
                                        <i class="fas fa-play-circle mr-2"></i>{{ __tr('Watch Video') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(!empty($videoLinks['demo_link_2']))
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="icon icon-shape icon-shape-info rounded-circle shadow mr-3">
                                            <i class="fas fa-video text-info" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="mb-0">{{ __tr('Demo Video 2') }}</h5>
                                    </div>
                                    <p class="text-muted mb-3">{{ __tr('Watch this demo video to learn more') }}</p>
                                    <a href="{{ $videoLinks['demo_link_2'] }}" target="_blank" class="btn btn-info btn-sm">
                                        <i class="fas fa-play-circle mr-2"></i>{{ __tr('Watch Video') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(!empty($videoLinks['demo_link_3']))
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="icon icon-shape icon-shape-warning rounded-circle shadow mr-3">
                                            <i class="fas fa-video text-warning" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="mb-0">{{ __tr('Demo Video 3') }}</h5>
                                    </div>
                                    <p class="text-muted mb-3">{{ __tr('Watch this demo video for advanced features') }}</p>
                                    <a href="{{ $videoLinks['demo_link_3'] }}" target="_blank" class="btn btn-warning btn-sm">
                                        <i class="fas fa-play-circle mr-2"></i>{{ __tr('Watch Video') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(!empty($videoLinks['demo_link_4']))
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="icon icon-shape icon-shape-danger rounded-circle shadow mr-3">
                                            <i class="fas fa-video text-danger" style="font-size: 2rem;"></i>
                                        </div>
                                        <h5 class="mb-0">{{ __tr('Demo Video 4') }}</h5>
                                    </div>
                                    <p class="text-muted mb-3">{{ __tr('Watch this demo video for additional tips') }}</p>
                                    <a href="{{ $videoLinks['demo_link_4'] }}" target="_blank" class="btn btn-danger btn-sm">
                                        <i class="fas fa-play-circle mr-2"></i>{{ __tr('Watch Video') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(empty(array_filter($videoLinks)))
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle mr-2"></i>
                                {{ __tr('No video links are currently available. Please contact the administrator to add video links in the Media settings.') }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

