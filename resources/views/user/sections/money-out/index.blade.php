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
            <h3 class="title">{{__($page_title)}}</h3>
        </div>
    </div>
    <div class="row mb-30-none">
        <div class="col-lg-6 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title"> Instant Transfer </h5>
                    </div>
                    <div class="dash-payment-body">
                        <form class="card-form" action="{{ setRoute('user.money.out.transfer') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-xl-12 col-lg-12 form-group text-center d-none">
                                    <div class="exchange-area">
                                        <code class="d-block text-center"><span></span> <span class="rate-show">--</span></code>
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-6 form-group d-none">
                                    <label>{{ __("Payment Process") }}<span>*</span></label>
                                    <select class="form--control nice-select gateway-select" name="gateway">
                                        {{-- <option disabled selected>Select Gateway</option> --}}
                                        @foreach ($payment_gateways ?? [] as $item)
                                            <option
                                                value="{{ $item->alias  }}"
                                                data-currency="{{ $item->currency_code }}"
                                                data-min_amount="{{ $item->min_limit }}"
                                                data-max_amount="{{ $item->max_limit }}"
                                                data-percent_charge="{{ $item->percent_charge }}"
                                                data-fixed_charge="{{ $item->fixed_charge }}"
                                                data-rate="{{ $item->rate }}" 
                                                >
                                                {{ $item->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl-12 col-lg-12 form-group">

                                    <label>{{ __("Amount") }}<span>*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form--control" required placeholder="Enter Amount" name="amount" value="{{ old("amount") }}">
                                        <select class="form--control nice-select">
                                            <option value="{{ get_default_currency_code() }}">{{ get_default_currency_code() }}</option>
                                        </select>
                                    </div>
                                    <code class="d-block mt-10 text-end text--dark fw-bold balance-show">{{ __("Available Balance") }} {{ authWalletBalance() }} {{ get_default_currency_code() }}</code>
                                </div>
                                <input type="hidden" name="gateway_name" value="flutterwave">
                                <div class="col-lg-12 form-group">
                                    <label for="bank_name">{{ __("Select Bank") }} <span class="text-danger">*</span></label>
                                    <select name="bank_name" class="form--control select2-basic" id="bankSelect" required data-placeholder="Select Bank" onchange="getAcctDetails()" >
                                          <option disabled selected value="">{{ __("Select Bank") }}</option>
                                        @foreach ($allBanks ??[] as $bank)
                                            <option value="{{ $bank['code'] }}">{{ $bank['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-12 form-group">
                                    <label for="account_number">{{ __("Account Number") }} <span class="text-danger">*</span></label>
                                    <input type="number" class="form--control check_bank" id="account_number" onkeyup="getAcctDetails()" name="account_number" value="{{ old('account_number') }}" placeholder="Account Number">
                                    <label class="exist text-start"></label>
                                </div>
                                <div class="col-lg-12 form-group " id="accName">
                                    <label for="account_name">{{ __("Account Name") }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form--control " id="account_name" value="{{ old('account_name') }}" name="account_name" placeholder="Account Name" readonly>
                                </div>
                                <div class="col-lg-12 form-group " id="">
                                    <label for="narration">{{ __("Narration") }} <span class="text-danger"></span></label>
                                    <input type="text" class="form--control " minlength="5" id="narration" value="{{ old('narration') }}" name="narration" placeholder="Narration" required>
                                </div>
                                
                                <div class="col-lg-12 form-group " id="">
                                    <label for="pin">{{ __("PIN") }} <span class="text-danger"></span></label>
                                    <input type="password" class="form--control " maxlength="4" id="pin" name="pin" placeholder="Trx PIN" required>
                                </div>
                                <div class="col-xl-12 col-lg-12 form-group">
                                    <div class="note-area">
                                        <code class="d-block limit-show">--</code>
                                        <code class="d-block fees-show">--</code>
                                    </div>
                                </div>
                                <div class="col-xl-12 col-lg-12">
                                    <button type="submit" class="btn--base w-100 btn-loading" disabled id="transferBtn">{{ __("Transfer") }} <i class="fas fa-arrow-alt-circle-right ms-1"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-30">
            <div class="dash-payment-item-wrapper">
                <div class="dash-payment-item active">
                    <div class="dash-payment-title-area">
                        <span class="dash-payment-badge">!</span>
                        <h5 class="title">{{ __($page_title) }} {{__("Preview")}}</h5>
                    </div>
                    <div class="dash-payment-body">
                        <div class="preview-list-wrapper">
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-receipt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Entered Amount") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="request-amount">--</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="lab la-get-pocket"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Conversion Amount") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="conversionAmount">--</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-battery-half"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span>{{ __("Total Fees & Charges") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="fees">--</span>
                                </div>
                            </div>

                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-money-check-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span class="">{{ __("Will Get") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--success will-get">--</span>
                                </div>
                            </div>
                            <div class="preview-list-item">
                                <div class="preview-list-left">
                                    <div class="preview-list-user-wrapper">
                                        <div class="preview-list-user-icon">
                                            <i class="las la-money-check-alt"></i>
                                        </div>
                                        <div class="preview-list-user-content">
                                            <span class="last">{{ __("Payable Amount") }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-list-right">
                                    <span class="text--warning last total-pay">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="dashboard-list-area mt-20">
        <div class="dashboard-header-wrapper">
            <h4 class="title ">{{__("Withdraw Money Log")}}</h4>
            <div class="dashboard-btn-wrapper">
                <div class="dashboard-btn mb-2">
                    <a href="{{ setRoute('user.transactions.index','withdraw') }}" class="btn--base">{{__("View More")}}</a>
                </div>
            </div>
        </div>
        <div class="dashboard-list-wrapper">
            @include('user.components.transaction-log',compact("transactions"))
        </div>
    </div>
</div>
@endsection

@push('script')
    <script>
         var defualCurrency = "{{ get_default_currency_code() }}";
         var defualCurrencyRate = "{{ get_default_currency_rate() }}";

        $('select[name=gateway]').on('change',function(){
            getExchangeRate($(this));
            getLimit();
            getFees();
            getPreview();
        });
        $(document).ready(function(){
            getExchangeRate();
            getLimit();
            getFees();
            getPreview();
        });
        $("input[name=amount]").keyup(function(){
             getFees();
             getPreview();
        });
        function getExchangeRate(event) {
            var element = event;
            var currencyCode = acceptVar().currencyCode;
            var currencyRate = acceptVar().currencyRate;
            var currencyMinAmount = acceptVar().currencyMinAmount;
            var currencyMaxAmount = acceptVar().currencyMaxAmount;
            $('.rate-show').html("1 " + defualCurrency + " = " + parseFloat(currencyRate).toFixed(4) + " " + currencyCode);
        }
        function getLimit() {
            var sender_currency = acceptVar().currencyCode;
            var sender_currency_rate = acceptVar().currencyRate;
            var min_limit = acceptVar().currencyMinAmount;
            var max_limit =acceptVar().currencyMaxAmount;
            if($.isNumeric(min_limit) || $.isNumeric(max_limit)) {
                var min_limit_calc = parseFloat(min_limit/sender_currency_rate).toFixed(4);
                var max_limit_clac = parseFloat(max_limit/sender_currency_rate).toFixed(4);
                $('.limit-show').html("Limit " + min_limit_calc + " " + defualCurrency + " - " + max_limit_clac + " " + defualCurrency);
                return {
                    minLimit:min_limit_calc,
                    maxLimit:max_limit_clac,
                };
            }else {
                $('.limit-show').html("--");
                return {
                    minLimit:0,
                    maxLimit:0,
                };
            }
        }

        function acceptVar() {
            var selectedVal = $("select[name=gateway] :selected");
            var currencyCode = $("select[name=gateway] :selected").attr("data-currency");
            var currencyRate = $("select[name=gateway] :selected").attr("data-rate");
            var currencyMinAmount = $("select[name=gateway] :selected").attr("data-min_amount");
            var currencyMaxAmount = $("select[name=gateway] :selected").attr("data-max_amount");
            var currencyFixedCharge = $("select[name=gateway] :selected").attr("data-fixed_charge");
            var currencyPercentCharge = $("select[name=gateway] :selected").attr("data-percent_charge");

            // var sender_select = $("input[name=from_wallet_id] :selected");

            return {
                currencyCode:currencyCode,
                currencyRate:currencyRate,
                currencyMinAmount:currencyMinAmount,
                currencyMaxAmount:currencyMaxAmount,
                currencyFixedCharge:currencyFixedCharge,
                currencyPercentCharge:currencyPercentCharge,
                selectedVal:selectedVal,

            };
        }

        function feesCalculation() {
            var sender_currency = acceptVar().currencyCode;
            var sender_currency_rate = acceptVar().currencyRate;
            var sender_amount = $("input[name=amount]").val();
            sender_amount == "" ? (sender_amount = 0) : (sender_amount = sender_amount);
            var conversion_amount = parseFloat( sender_amount) *  parseFloat(sender_currency_rate)

            var fixed_charge = acceptVar().currencyFixedCharge;
            var percent_charge = acceptVar().currencyPercentCharge;
            if ($.isNumeric(percent_charge) && $.isNumeric(fixed_charge) && $.isNumeric(conversion_amount)) {
                // Process Calculation
                var fixed_charge_calc = parseFloat(fixed_charge);
                var percent_charge_calc = (parseFloat(conversion_amount) / 100) * parseFloat(percent_charge);
                var total_charge = parseFloat(fixed_charge_calc) + parseFloat(percent_charge_calc);
                total_charge = parseFloat(total_charge).toFixed(4);
                // return total_charge;
                return {
                    total: total_charge,
                    fixed: fixed_charge_calc,
                    percent: percent_charge,
                };
            } else {
                // return "--";
                return false;
            }
        }

        function getFees() {
            var sender_currency = acceptVar().currencyCode;
            var percent = acceptVar().currencyPercentCharge;
            var charges = feesCalculation();
            if (charges == false) {
                return false;
            }
            $(".fees-show").html("Charge: " + parseFloat(charges.fixed).toFixed(4) + " " + sender_currency + " + " + parseFloat(charges.percent).toFixed(4) + "%");
        }
        function getPreview() {
                var senderAmount = $("input[name=amount]").val();
                var sender_currency = acceptVar().currencyCode;
                var sender_currency_rate = acceptVar().currencyRate;
                // var receiver_currency = acceptVar().rCurrency;
                senderAmount == "" ? senderAmount = 0 : senderAmount = senderAmount;

                // Sending Amount
                $('.request-amount').text(senderAmount + " " + defualCurrency);

                // Fees
                var charges = feesCalculation();
                var total_charge = 0;
                if(senderAmount == 0){
                    total_charge = 0;
                }else{
                    total_charge = charges.total;
                }

                $('.fees').text(total_charge + " " + sender_currency);

                var conversionAmount = senderAmount * sender_currency_rate;
                $('.conversionAmount').text(parseFloat(conversionAmount).toFixed(4) + " " + sender_currency);
                // willget
                var will_get = parseFloat(senderAmount) * parseFloat(sender_currency_rate)
                var will_get_total = 0;
                if(senderAmount == 0){
                     will_get_total = 0;
                }else{
                     will_get_total =  parseFloat(will_get) - parseFloat(charges.total);
                }
                $('.will-get').text(parseFloat(will_get_total).toFixed(4) + " " + sender_currency);

                // total payable
                var totalPay = parseFloat(senderAmount)
                var pay_in_total = 0;
                if(senderAmount == 0){
                     pay_in_total = 0;
                }else{
                    //  pay_in_total =  parseFloat(totalPay) + parseFloat(charges.total);
                     pay_in_total =  parseFloat(totalPay);
                }
                $('.total-pay').text(parseFloat(pay_in_total).toFixed(4) + " " + defualCurrency);
        }

    </script>
    <script>
        function getAcctDetails(){
            var number = $('#account_number').val();
            if(number.length == 10){
                var accName = $('#account_name');
                var select = $('#bankSelect');
                var option = select.find(':selected');
                var bankCode = option.attr("value");
                var subBtn = $("#transferBtn");
                subBtn.prop('disabled', true);
                
                //validate account
                accName.val("Getting account details...");
                var postObj = {
                    number: number,
                    bank: bankCode,
                    _token:"{{ csrf_token() }}"
                }
                jQuery.ajax({
                    url: "{{setRoute('user.money.out.transfer.validate')}}",
                    data: postObj,
                    type: "POST",
                    success: function (res) {
                        console.log(res);
                        if (res.status === "success") {
                            accName.val(res.data.account_name);
                            subBtn.prop('disabled', false);
                        }
                        else{
                            accName.val('Check Account Number');
                            subBtn.prop('disabled', true);
                        }
                    },
                    error: function () {
                        accName.val('Check Account Number');
                        subBtn.prop('disabled', true);
                    }
                    
                });
            }else{
                $("#account_name").val('Account Number must be 10 digits');
                $("#transferBtn").attr('disabled' , true)
            }
        }
    </script>
@endpush
