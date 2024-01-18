@extends('user.layouts.master')

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __(@$page_title)])
@endsection

@section('content')
<div class="body-wrapper">
    <div class="dashboard-area mt-10">

    </div>
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title">{{ __(@$page_title) }}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <div class="exchange-area-wrapper text-center">
                            <div class="exchange-area mb-20">
                                <code class="d-block text-center"><span>{{ __("Current Balance") }}</span>
                                    {{ getAmount(@$myCard->balance,2) }} {{ get_default_currency_code() }}
                                </code>
                            </div>
                        </div>
                        <div class="preview-list-wrapper">
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-hourglass-end"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Card Type") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--warning">{{ __((ucwords(@$myCard->card_type))) }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-credit-card"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Card Id") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ @$myCard->card_id }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-hourglass-end "></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Customer Id") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ @$myCard->customer_id }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-user-tag"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Card Number") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    @php
                                    $card_pan = str_split($myCard->card_number, 4);
                                   @endphp
                                       @foreach($card_pan as $key => $value)
                                       <span>{{ @$value }}</span>
                                       @endforeach
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-hourglass-start"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Cvv") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ __(@$myCard->cvv) }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-business-time"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{__("Expiration")}}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{@$myCard->expiry }}</span>
                                </div>
                            </div>


                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-city"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("City") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ @$myCard->user->strowallet_customer->city??"" }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-city"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("State") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ $myCard->user->strowallet_customer->state??"" }}</span>
                                </div>
                            </div>

                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-file-archive"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{__("Zip Code")}}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span>{{ @$myCard->user->strowallet_customer->zipCode??"" }}</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-battery-half"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Status") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <div class="toggle-container">

                                        @include('admin.components.form.switcher',[

                                            'name'          => 'is_active',
                                            'value'         => old('is_active',@$myCard->is_active ),
                                            'options'       => ['UnFreeze ' => 1,'Freeze' => 0],
                                            'onload'        => true,
                                            'data_target'   => @$myCard->id,
                                        ])
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')

<script>
     $(document).ready(function(){
        switcherAjax("{{ setRoute('user.strowallet.virtual.card.change.status') }}");
    })
</script>

@endpush
