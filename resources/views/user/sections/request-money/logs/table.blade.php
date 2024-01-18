<table class="custom-table">
    <thead>
        <tr>
            <th>Transaction ID</th>
            <th>Request Type</th>
            <th>Request Amount</th>
            <th>Fees & Charge</th>
            <th>Payable</th>
            <th>Status</th>
            <th>Time</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($transactions as $item)
            <tr data-id ={{ $item->id }}>
                <td>{{ $item->trx_id }}</td>
                @if ($item->attribute == payment_gateway_const()::SEND)
                <td><span class="text--info">{{ payment_gateway_const()::SEND }}</span></td>
                @else
                <td><span class="text--info">{{ payment_gateway_const()::RECEIVED }}</span></td>
                @endif
                <td><span> {{ get_amount($item->request_amount,$item->creator_wallet->currency->code) }}</span></td>
                @if ($item->attribute == payment_gateway_const()::SEND)
                    <td><span class="text--info">{{ __("N/A") }}</span></td>

                @else
                <td>{{ get_amount($item->details->charges->total_charge,$item->creator_wallet->currency->code) }}</td>
                @endif
                @if ($item->attribute == payment_gateway_const()::SEND)
                    <td><span class="text--info">{{ __("N/A") }}</span></td>
                @else
                <td>{{ get_transaction_numeric_attribute_request_money($item->attribute) }} {{ get_amount($item->payable,$item->creator_wallet->currency->code) }}</td>
                @endif
                <td>
                    <span class="{{ $item->stringStatus->class }}">{{ $item->stringStatus->value }}</span>
                </td>
                <td>{{ $item->created_at->format("Y-m-d H:i A") }}</td>

                @if ($item->attribute == payment_gateway_const()::RECEIVED)
                    <td>
                        <button  class="btn btn--success approved-trx" {{ $item->status ==  payment_gateway_const()::STATUSPENDING ? '' : 'disabled'}}><i class="las la-check"></i></button>
                        <button  class="btn btn--danger rejected-trx" {{ $item->status ==  payment_gateway_const()::STATUSPENDING ? '' : 'disabled'}}><i class="las la-times"></i></button>
                    </td>
                @else
                    <td><span class="text--info">{{ __("N/A") }}</span></td>
                @endif

            </tr>
        @empty
        @include('admin.components.alerts.empty',['colspan' => 8])
        @endforelse
    </tbody>
</table>
@push('script')
    <script>
        $(".approved-trx").click(function(){
            var id = $(this).parents('tr').attr('data-id');
            var actionRoute =  "{{ setRoute('user.request.money.log.approve') }}";
            var target      = id;
            var btnText     = "Approve";
            var message     = `Are you sure to <strong>${btnText}</strong> this request?`;
            openAlertModal(actionRoute,target,message,btnText,"POST");
        });
    </script>
    <script>
        $(".rejected-trx").click(function(){
            var id = $(this).parents('tr').attr('data-id');
            var actionRoute =  "{{ setRoute('user.request.money.log.reject') }}";
            var target      = id;
            var btnText     = "Reject";
            var message     = `Are you sure to <strong>${btnText}</strong> this request?`;
            openAlertModal(actionRoute,target,message,btnText,"POST");
        });
    </script>
@endpush
