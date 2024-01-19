@extends('user.layouts.master')

@push('css')

@endpush

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
        <div class="dashboard-header-wrapper">
            <h3 class="title">{{__(@$page_title)}}</h3>
        </div>
    </div>
    <div class="row mb-30-none">
        <div class="col-xl-8 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title">{{ __("Cable Subscription") }}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <form class="card-form" action="{{ setRoute('user.bill.pay.cable') }}" id="buyData" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-12 form-group">
                                    <label class="form-label" for="network">Decoders  <span class="text--base">*</span></label>
                                    <select class="form-select form--control" name="decoder" id="networkSelector" data-placeholder="Select Network" required>
                                        <option > Select network </option>
                                        @foreach ($decoders as $item)
                                        <option value="{{$item->id}}" >{{$item->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 form-group " id="div_data_plan_selector">
                                    <label class="form-label" for="data-plan">Select Plan</label>
                                    <select class="form--control form-select" name="plan" id="data-plan-selector" data-placeholder="Select Plan" required>
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

                                <div class="col-xl-12 col-lg-12">
                                    <button type="submit" class="btn--base w-100 btn-loading" id="sbutton">{{ __("Confirm") }} <i class="fas fa-tv ms-1"></i></button>
                                </div>
                            </div>
                        </form>
                        
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
                    <a href="{{ setRoute('user.transactions.index','bills-payment') }}" class="btn--base">{{__("View More")}}</a>
                </div>
            </div>
        </div>
        <div class="dashboard-list-wrapper">
            @include('user.components.transaction-log',compact("transactions"))
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
<style>
     #div_data_plan_selector{
        display:none;
    }
</style>
@endsectio
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

@endpush
