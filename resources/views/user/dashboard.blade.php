@extends('user.layouts.master')

@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __("Dashboard")])
@endsection

@section('content')
<div class="body-wrapper">
    <div class="dashboard-area mt-10">
        <div class="dashboard-header-wrapper">
            <h3 class="title">{{ __("Overview") }}</h3>
        </div>
        <div class="dashboard-item-area">
            <div class="row mb-20-none">
                <div class="col-xxl-2 col-xl-4 col-lg-4 col-md-4 col-sm-12 mb-10">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <span class="sub-title">{{__("Balance")}}</span>
                            <h3 class="title"> {{ @$baseCurrency->code }} <span class="text--base">{{ authWalletBalance() }} </span></h3>
                        </div>

                        <div class="dashboard-icon">
                           <i class="fa-regular fa-money-bill-trend-up"></i>
                        </div>
                    </div>
				</div>
                <div class="col-xxl-3 col-xl-4 col-lg-6 col-md-6 col-sm-12 mb-20">
                    <div class="dashbord-item">
                        <div class="dashboard-content">
                            <span class="sub-title">{{ __("Virtual Card") }}</span>
                            <h3 class="title"> {{ @$baseCurrency->code }} <span class="text--base"> {{ getAmount($data['cardAmount'],2) }}</span></h3>
                        </div>
                        <div class="dashboard-icon">
                            <i class="fas fa-credit-card"></i>
                                   </div>
            </div>
        </div>
        <div class="dashboard-list-wrapper">
            @include('user.components.transaction-log',compact("transactions"))
        </div>
        </div>
         </div>
    <div class="dashboard-list-area mt-20">
        <div class="dashboard-header-wrapper">
            <h5 class="title">Recent Transactions</h5>
            <div class="dashboard-btn-wrapper">
                <div class="dashboard-btn mb-2">
                    <a href="{{ setRoute('user.transactions.index') }}" class="btn--base">{{__("View More")}}</a>
                </div>
            </div>    
    </div>
    <div class="chart-area mt-30">
        <div class="row mb-20-none">
            <div class="col-xxl-7 col-xl-7 col-lg-7 mb-20">
                <div class="chart-wrapper">
                    <div class="dashboard-header-wrapper">
                        <h4 class="title">{{ __("Deposit Chart") }}</h4>
                    </div>
                    <div class="chart-container">
                        <div id="chart1" data-chart_one_data="{{ json_encode($chartData['chart_one_data']) }}" data-month_day="{{ json_encode($chartData['month_day']) }}" class="chart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-5 col-xl-5 col-lg-5 mb-20">
                <div class="chart-wrapper">
                    <div class="dashboard-header-wrapper">
                        <h4 class="title">{{ __("Transfer Chart") }}</h4>
                    </div>
                    <div class="chart-container">
                        <div id="chart3" data-chart_three_data="{{ json_encode($chartData['chart_two_data']) }}" data-month_day="{{ json_encode($chartData['month_day']) }}" class="chart"></div>
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
    var chart1 = $('#chart1');
    var chart_one_data = chart1.data('chart_one_data');
    var month_day = chart1.data('month_day');
    var options = {
        series: [
            {
            name: 'Pending',
            color: "#0C56DB",
            data: chart_one_data.pending_data
            }, {
            name: 'Completed',
            color: "rgba(0, 227, 150, 0.85)",
            data: chart_one_data.success_data
            }, {
            name: 'Canceled',
            color: "#dc3545",
            data: chart_one_data.canceled_data
            }, {
            name: 'Hold',
            color: "#ded7e9",
            data: chart_one_data.hold_data
            }
        ],
        chart: {
            height: 350,
            type: "area",
            toolbar: {
                show: false,
            },
        },
        dataLabels: {
            enabled: false,
        },
        stroke: {
            curve: "smooth",
        },
        xaxis: {
            type: "datetime",
            categories:month_day,
        },
        tooltip: {
            x: {
                format: "dd/MM/yy HH:mm",
            },
        },
    };

    var chart = new ApexCharts(document.querySelector("#chart1"), options);
    chart.render();
//money out
var chart3 = $("#chart3");
var chart_three_data = chart3.data("chart_three_data");
var month_day = chart3.data("month_day");
// apex-chart
var options = {
    series: [
        {
            name: 'Pending',
            color: "#0C56DB",
            data: chart_three_data.pending_data
            }, {
            name: 'Completed',
            color: "rgba(0, 227, 150, 0.85)",
            data: chart_three_data.success_data
            }, {
            name: 'Canceled',
            color: "#dc3545",
            data: chart_three_data.canceled_data
            }, {
            name: 'Hold',
            color: "#ded7e9",
            data: chart_three_data.hold_data
            }
    ],
    chart: {
        type: "bar",
        height: 350,
        stacked: true,
        toolbar: {
            show: false,
        },
        zoom: {
            enabled: true,
        },
    },
    responsive: [
        {
            breakpoint: 480,
            options: {
                legend: {
                    position: "bottom",
                    offsetX: -10,
                    offsetY: 0,
                },
            },
        },
    ],
    plotOptions: {
        bar: {
            horizontal: false,
            borderRadius: 10,
        },
    },
    xaxis: {
        type: "datetime",
        categories: month_day,
    },
    legend: {
        position: "bottom",
        offsetX: 40,
    },
    fill: {
        opacity: 1,
    },
};

var chart = new ApexCharts(document.querySelector("#chart3"), options);
chart.render();

</script>
@endpush
