<table class="custom-table transaction-search-table">
    <thead>
        <tr>
            <th>SL NO</th>
            <th>TXN Type</th>
            <th>Sender</th>
            <th>Receiver</th>
            <th>TXN Hash</th>
            <th>Asset</th>
            <th>Chain</th>
            <th>Amount</th>
            <th>Block Number</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($incoming_transactions ?? [] as $key => $item)
            <tr>
                <td>
                    {{ $key + $incoming_transactions->firstItem() }}
                </td>
                <td>{{ $item->transaction_type }}
                <td>{{ $item->sender_address }}</td>
                <td>{{ $item->receiver_address }}</td>
                <td>{{ $item->txn_hash }}</td>
                <td>{{ $item->asset }}</td>
                <td>{{ $item->chain }}</td>
                <td>{{ $item->amount }}</td>
                <td>{{ $item->block_number }}</td>
                <td>
                    @if ($item->status == payment_gateway_const()::NOT_USED)
                        <div class="badge badge--danger">
                            {{ __("Not Used") }}
                        </div>
                    @elseif ($item->status == payment_gateway_const()::USED)
                        <div class="badge badge--success">
                            {{ __("Used") }}
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            @include('admin.components.alerts.empty',['colspan' => 12])
        @endforelse
    </tbody>
</table>