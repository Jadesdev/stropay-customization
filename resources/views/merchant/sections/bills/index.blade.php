@extends('merchant.layouts.master')

@push('css')

@endpush

@section('breadcrumb')
    @include('merchant.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("merchant.dashboard"),
        ]
    ], 'active' => __(@$page_title)])
@endsection

@section('content')
<div class="body-wrapper">
    <div class="dashboard-area mt-10">
        <div class="dashboard-header-wrapper">
            <h3 class="title">{{__(@$page_title)}}</h3>
        </div>
    </div>
    <div class="row mb-30-none">
        <div class="col-6 col-md-3 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge"><i class="fa fa-coins"></i></span>
                        <h5 class="title">{{ __("Buy Airtime") }}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <div class="img-body mb-3">
                            <img src="{{asset('public/services/airtime.jpg')}}" class="bill-img" alt="">
                        </div>
                        <a class="btn btn-primary btn-sm w-100" href="#" data-bs-toggle="modal" data-bs-target="#BuyAirtimeModal">
                           Buy Airtime
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge"><i class="fa fa-wifi"></i></span>
                        <h5 class="title">{{ __("Buy Data") }}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <div class="img-body mb-3">
                            <img src="{{asset('public/services/data.jpg')}}" class="bill-img" alt="">
                        </div>
                        <a class="btn btn-primary btn-sm w-100" href="#" data-bs-toggle="modal" data-bs-target="#BuyDataModal">
                           Buy Data
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge"><i class="fa fa-tv"></i></span>
                        <h5 class="title">{{ __("Cable Payment") }}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <div class="img-body mb-3">
                            <img src="{{asset('public/services/cabletv.jpg')}}" class="bill-img" alt="">
                        </div>
                        <a class="btn btn-primary btn-sm w-100" href="#" data-bs-toggle="modal" data-bs-target="#BuyCableModal">
                           Cable Payment
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge"><i class="fa fa-lightbulb"></i></span>
                        <h5 class="title">{{ __("Bills Payment") }}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <div class="img-body mb-3">
                            <img src="{{asset('public/services/power.jpg')}}" class="bill-img" alt="">
                        </div>
                        <a class="btn btn-primary btn-sm w-100" href="#" data-bs-toggle="modal" data-bs-target="#BuyPowerModal">
                           Bills Payement
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="dashboard-list-area mt-20">
        <div class="dashboard-header-wrapper">
            <h4 class="title ">{{__("Bill Pay Log")}}</h4>
            <div class="dashboard-btn-wrapper">
                <div class="dashboard-btn mb-2">
                    <a href="{{ setRoute('merchant.transactions.index','bills-payment') }}" class="btn--base">{{__("View More")}}</a>
                </div>
            </div>
        </div>
        <div class="dashboard-list-wrapper">
            @include('merchant.components.transaction-log',compact("transactions"))
        </div>
    </div>
</div>

<div class="modal fade" id="scanModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
            <div class="modal-body text-center">
                <video id="preview" class="p-1 border" style="width:300px;"></video>
            </div>
            <div class="modal-footer justify-content-center">
              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">@lang('close')</button>
            </div>
      </div>
    </div>
</div>
<div class="modal fade" id="BuyAirtimeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header"> 
            <h5> Buy Airtime</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <form class="card-form" action="{{ setRoute('merchant.bills.airtime') }}" id="buyAirtime" method="POST">
                @csrf
                <div class="row">
                    <div class="col-12 form-group">
                        <label class="form-label" for="network">Network  <span class="text--base">*</span></label>
                        <select class="form-select form--control" name="network" id="" data-placeholder="Select Network" required>
                            @foreach ($networks as $item)
                                <option value="{{$item->id}}" discount="{{$item->discount}}">{{$item->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 form-group">
                        <label>Phone Number <span class="text--base">*</span></label>
                        <input type="number" class="form--control" maxlength="11" required name="phone" placeholder="Enter Phone Number" value="{{ old('phone') }}">

                    </div>

                    <div class="col-xxl-12 col-xl-12 col-lg-12  form-group">
                        <label>{{ __("Amount") }}<span>*</span></label>
                        <div class="input-group">
                            <input type="number" class="form--control" placeholder="Enter Amount" name="amount" value="{{ old("amount") }}">
                            <select class="form--control nice-select currency" name="currency">
                                <option value="{{ get_default_currency_code() }}">{{ get_default_currency_code() }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        <div class="note-area">
                            <code class="d-block fw-bold">{{ __("Available Balance") }}: {{ authWalletBalance() }} {{ get_default_currency_code() }}</code>
                        </div>
                    </div>

                    <div class="col-lg-12 form-group " id="">
                        <label for="pin">{{ __("PIN") }} <span class="text-danger"></span></label>
                        <input type="text" class="form--control " maxlength="4"  name="pin" placeholder="Trx PIN" required>
                    </div>
                    
                    <div class="col-xl-12 col-lg-12">
                        <button type="submit" class="btn--base w-100 btn-loading" id="sbuttonA">{{ __("Buy Airtime") }} <i class="fas fa-coins ms-1"></i></button>
                    </div>
                </div>
            </form>
        </div>
      </div>
    </div>
</div>

<div class="modal fade" id="BuyDataModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header"> 
            <h5> Buy Data</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <form class="card-form" action="{{ setRoute('merchant.bills.data') }}" id="buyData" method="POST">
                @csrf
                <div class="row">
                    <div class="col-12 form-group">
                        <label class="form-label" for="network">Network  <span class="text--base">*</span></label>
                        <select class="form-select form--control" name="network" id="networkSelector" data-placeholder="Select Network" required>
                            <option > Select network </option>
                            @foreach ($networks as $item)
                            <option value="{{$item->id}}" discount="{{$item->discount}}">{{$item->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 form-group " id="div_data_plan_selector">
                        <label class="form-label" for="data-plan">Data Plan</label>
                        <select class="form--control form-select" name="plan" id="data-plan-selector" data-placeholder="Select Plan" required>
                            <option value="" data-network="0" > Select plan </option>
                            @foreach ($dataplan as $item)
                            <option value="{{$item->id}}" data-price="{{$item->price}}" data-network="{{$item->network_id}}">{{$item->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 form-group">
                        <label>Phone Number <span class="text--base">*</span></label>
                        <input type="number" class="form--control" maxlength="11" required name="phone" placeholder="Enter Phone Number" value="{{ old('phone') }}">

                    </div>

                    <div class="col-xxl-12 col-xl-12 col-lg-12  form-group">
                        <label>{{ __("Amount") }}<span>*</span></label>
                        <div class="input-group">
                            <input type="text" class="form--control" placeholder="Amount" readonly id="data-amount" name="amount" value="{{ old("amount") }}">
                            <select class="form--control nice-select currency" name="currency">
                                <option value="{{ get_default_currency_code() }}">{{ get_default_currency_code() }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        <div class="note-area">
                            <code class="d-block fw-bold">{{ __("Available Balance") }}: {{ authWalletBalance() }} {{ get_default_currency_code() }}</code>
                        </div>
                    </div>
                    <div class="col-lg-12 form-group " id="">
                        <label for="pin">{{ __("PIN") }} <span class="text-danger"></span></label>
                        <input type="text" class="form--control " maxlength="4"  name="pin" placeholder="Trx PIN" required>
                    </div>

                    <div class="col-xl-12 col-lg-12">
                        <button type="submit" class="btn--base w-100 btn-loading" id="sbuttonD">{{ __("Buy Data") }} <i class="fas fa-wifi ms-1"></i></button>
                    </div>
                </div>
            </form>
        </div>
      </div>
    </div>
</div>

<div class="modal fade" id="BuyCableModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header"> 
            <h5> Cable Subscription</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <form class="card-form" action="{{ setRoute('merchant.bills.cable') }}" id="buyData" method="POST">
                @csrf
                <div class="row">
                    <div class="col-12 form-group">
                        <label class="form-label" for="network">Decoders  <span class="text--base">*</span></label>
                        <select class="form-select form--control" name="decoder" id="CableSelector" data-placeholder="Select Decoder" required>
                            <option > Select Decoders </option>
                            @foreach ($decoders as $item)
                            <option value="{{$item->id}}" >{{$item->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 form-group " id="div_cable_plan_selector">
                        <label class="form-label" for="data-plan">Select Plan</label>
                        <select class="form--control form-select" name="plan" id="cable-plan-selector" data-placeholder="Select Plan" required>
                            <option value="" data-network="0" > Select plan </option>
                            @foreach ($cableplan as $item)
                            <option value="{{$item->id}}" data-price="{{$item->price}}" data-network="{{$item->decoder_id}}">{{$item->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 form-group">
                        <label>Decoder Number <span class="text--base">*</span></label>
                        <input type="number" class="form--control" required name="number" placeholder="Enter Decoder Number" value="{{ old('number') }}">

                    </div>

                    <div class="col-xxl-12 col-xl-12 col-lg-12  form-group">
                        <label>{{ __("Amount") }}<span>*</span></label>
                        <div class="input-group">
                            <input type="text" class="form--control" placeholder="Amount" readonly id="data-amount-c" name="amount" value="{{ old("amount") }}">
                            <select class="form--control nice-select currency" name="currency">
                                <option value="{{ get_default_currency_code() }}">{{ get_default_currency_code() }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        <div class="note-area">
                            <code class="d-block fw-bold">{{ __("Available Balance") }}: {{ authWalletBalance() }} {{ get_default_currency_code() }}</code>
                        </div>
                    </div>
                    <div class="col-lg-12 form-group " id="">
                        <label for="pin">{{ __("PIN") }} <span class="text-danger"></span></label>
                        <input type="text" class="form--control " maxlength="4"  name="pin" placeholder="Trx PIN" required>
                    </div>

                    <div class="col-xl-12 col-lg-12">
                        <button type="submit" class="btn--base w-100 btn-loading" id="sbuttonC">{{ __("Make Payment") }} <i class="fas fa-tv ms-1"></i></button>
                    </div>
                </div>
            </form>
        </div>
      </div>
    </div>
</div>

<div class="modal fade" id="BuyPowerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header"> 
            <h5> Bills Payment</h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <form class="card-form" action="{{ setRoute('merchant.bills.power') }}" id="buyAirtime" method="POST">
                @csrf
                <div class="row">
                    <div class="col-12 form-group">
                        <label class="form-label" for="network">Disco Company <span class="text--base">*</span></label>
                        <select class="form-select form--control" name="disco" id="" data-placeholder="Select Network" required>
                            @foreach ($powers as $item)
                                <option value="{{$item->id}}" fee="{{$item->fee}}">{{$item->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 form-group">
                        <label>Meter Number <span class="text--base">*</span></label>
                        <input type="number" class="form--control" required name="number" placeholder="Enter Meter Number" value="{{ old('number') }}">

                    </div>
                    <div class="form-group">
                        <label for="" class="form-label">Meter Type<span class="text-danger">*</span></label>
                        <select name="type" id="meterType" class="form-select form--control" required>
                            <option > Select Meter Type</option>
                            <option value="1">Prepaid</option>
                            <option value="2">Postpaid</option>
                        </select>
                    </div>

                    <div class="col-xxl-12 col-xl-12 col-lg-12  form-group">
                        <label>{{ __("Amount") }}<span>*</span></label>
                        <div class="input-group">
                            <input type="number" class="form--control" placeholder="Enter Amount" name="amount" value="{{ old("amount") }}" required>
                            <select class="form--control nice-select currency" name="currency">
                                <option value="{{ get_default_currency_code() }}">{{ get_default_currency_code() }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xl-12 col-lg-12 form-group">
                        <div class="note-area">
                            <code class="d-block fw-bold">{{ __("Available Balance") }}: {{ authWalletBalance() }} {{ get_default_currency_code() }}</code>
                        </div>
                    </div>

                    <div class="col-lg-12 form-group " id="">
                        <label for="pin">{{ __("PIN") }} <span class="text-danger"></span></label>
                        <input type="text" class="form--control " maxlength="4"  name="pin" placeholder="Trx PIN" required>
                    </div>
                    <div class="col-xl-12 col-lg-12">
                        <button type="submit" class="btn--base w-100 btn-loading" id="sbuttonP">{{ __("Pay Bill") }} <i class="fas fa-lightbulb ms-1"></i></button>
                    </div>
                </div>
            </form>
        </div>
      </div>
    </div>
</div>
<style>
    #div_data_plan_selector{
        display:none;
    }
    #div_cable_plan_selector{
        display:none;
    }
    .bill-img{
        border-radius: 25px;
    }
</style>
@endsection

@push('script')
<script>
    var defualCurrency = "{{ get_default_currency_code() }}";
    var defualCurrencyRate = "{{ get_default_currency_rate() }}";

    // Network selector
    $("#networkSelector").change(function(){
        var network =$("#networkSelector option:selected").val();
        $("#div_data_plan_selector").css("display", "block");
        //Hide the rest
        $('#data-plan-selector option').css('display', 'none');
        $('#data-plan-selector option').attr('disabled', true);
        //Show defaukt one
        $('#data-plan-selector option[data-network="0"]').css('display', 'block');
        $('#data-plan-selector option[data-network="0"]').attr('disabled', false);
        //SHow active ones
        if(network == '1'){
            $('#data-plan-selector option[data-network="1"]').css('display', 'block');
             $('#data-plan-selector option[data-network="1"]').attr('disabled', false);
            $("#div_data_plan_selector").css("display", "block");
        }else if(network == '2'){
            $('#data-plan-selector option[data-network="2"]').css('display', 'block');
             $('#data-plan-selector option[data-network="2"]').attr('disabled', false);
            $("#div_data_plan_selector").css("display", "block");
        }else if(network == '3'){
            $('#data-plan-selector option[data-network="3"]').css('display', 'block');
             $('#data-plan-selector option[data-network="3"]').attr('disabled', false);
            $("#div_data_plan_selector").css("display", "block");
        }else if(network == '4'){
            $('#data-plan-selector option[data-network="4"]').css('display', 'block');
             $('#data-plan-selector option[data-network="4"]').attr('disabled', false);
            $("#div_data_plan_selector").css("display", "block");
        }
        
    });
    
    // plan selector
    $("#data-plan-selector").change(function(){
        var dataid = $("#data-plan-selector").find(":selected").val();
        var amount = $("#data-plan-selector").find(":selected").attr('data-price');
        $("#data-amount").val(amount);
    });
</script>
<script>
    var defualCurrency = "{{ get_default_currency_code() }}";
    var defualCurrencyRate = "{{ get_default_currency_rate() }}";

    // Network selector
    $("#CableSelector").change(function(){
        var network =$("#CableSelector option:selected").val();
        $("#div_cable_plan_selector").css("display", "block");
        //Hide the rest
        $('#cable-plan-selector option').css('display', 'none');
        $('#cable-plan-selector option').attr('disabled', true);
        //Show defaukt one
        $('#cable-plan-selector option[data-network="0"]').css('display', 'block');
        $('#cable-plan-selector option[data-network="0"]').attr('disabled', false);
        //SHow active ones
        if(network == '1'){
            $('#cable-plan-selector option[data-network="1"]').css('display', 'block');
             $('#cable-plan-selector option[data-network="1"]').attr('disabled', false);
            $("#div_cable_plan_selector").css("display", "block");
        }else if(network == '2'){
            $('#cable-plan-selector option[data-network="2"]').css('display', 'block');
             $('#cable-plan-selector option[data-network="2"]').attr('disabled', false);
            $("#div_cable_plan_selector").css("display", "block");
        }else if(network == '3'){
            $('#cable-plan-selector option[data-network="3"]').css('display', 'block');
             $('#cable-plan-selector option[data-network="3"]').attr('disabled', false);
            $("#div_cable_plan_selector").css("display", "block");
        }else if(network == '4'){
            $('#cable-plan-selector option[data-network="4"]').css('display', 'block');
             $('#cable-plan-selector option[data-network="4"]').attr('disabled', false);
            $("#div_cable_plan_selector").css("display", "block");
        }
        
    });
    
    // plan selector
    $("#cable-plan-selector").change(function(){
        var dataid = $("#cable-plan-selector").find(":selected").val();
        var amount = $("#cable-plan-selector").find(":selected").attr('data-price');
        $("#data-amount-c").val(amount);
    });
</script>
@endpush
