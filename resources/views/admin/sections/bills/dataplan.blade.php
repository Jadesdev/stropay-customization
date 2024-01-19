@extends('admin.layouts.master')

@push('css')
    <style>
        .fileholder {
            min-height: 194px !important;
        }

        .fileholder-files-view-wrp.accept-single-file .fileholder-single-file-view,.fileholder-files-view-wrp.fileholder-perview-single .fileholder-single-file-view{
            height: 150px !important;
        }
    </style>
@endpush

@section('page-title')
    @include('admin.components.page-title',['title' => __($page_title)])
@endsection

@section('breadcrumb')
    @include('admin.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("admin.dashboard"),
        ]
    ], 'active' => __("Manage Dataplans")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Manage Dataplans") }}</h5>
                <div class="table-btn-area">
                    @include('admin.components.search-input',[
                        'name'  => 'search',
                    ])
                    <a href="#" data-bs-target="#add-dataplan" data-bs-toggle="modal" class="btn--base modal-btn"><i class="fas fa-plus me-1"></i> Add Dataplan</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="datatable">
                    <thead class="white">
                        <tr>
                        <th>S/N</th>
                        <th>Name</th>
                        <th>Network</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dataplans as $key => $plan)
                        <tr>
                            <td>{{$key +1}}</td>
                            <td>{{$plan->name}}</td>
                            <td>{{$plan->network->name ?? ""}}</td>
                            <td>{{$plan->service}}</td>
                            <td> {{($plan->price)}}</td>
                            <td><span class="badge @if($plan->status == 1)bg-success @else bg-danger @endif">@if($plan->status == 1)active @else disabled @endif </span></td>
                            <td>
                                <div class="">
                                    <a class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#editPlan-{{$plan->id}}"  href="#" title="@lang('Edit Plan')" > <i class="fa fa-edit"></i></a>
                                   
                                    <a class="btn btn-sm btn-danger" href="{{route('admin.bills.dataplan.delete' ,[$plan->id])}}" title="Delete"><i class="fa fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <div class="modal fade" id="editPlan-{{$plan->id}}" tabindex="-1"  aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                <div class="modal-header p-2">
                                    <h5 class="modal-title fw-bold">Edit Data Plan</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form action="{{route('admin.bills.dataplan.update', $plan->id)}}"enctype="multipart/form-data" method="post">
                                        @csrf
                                        <div class="row">
                                            <div class="form-group col-sm-6">
                                                <label class="form-label">Name</label>
                                                <input type="text" class="form-control" name="name" value="{{$plan->name}}" placeholder="Plan Name" required>
                                            </div>
                                            <div class="form-group col-sm-6">
                                                <label class="form-label">Network</label>
                                                <select name="network_id" class="form-select" required>
                                                    @foreach($networks as $item)
                                                    <option value="{{$item->id}}" @if($plan->network_id == $item->id ) selected @endif>{{$item->name}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group col-6">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select" required>
                                                     <option value="1" {{$plan->status == '1' ? "selected" : ""}}>Active</option>
                                                     <option value="2" {{$plan->status == '2' ? "selected" : ""}}>Disabled</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-6">
                                                <label class="form-label">Data Type</label>
                                                <select name="service" class="form-select" required>
                                                    <option value="SME" {{$plan->service == 'SME' ? "selected" : ""}}>SME</option>
                                                    <option value="CG"  {{$plan->service == 'CG' ? "selected" : ""}}>Corporate Gifting</option>
                                                    <option value="GIFTING" {{$plan->service == 'GIFTING' ? "selected" : ""}}>Gifting</option>
                                                    {{-- <option value="SME2" {{$plan->service == 'SME2' ? "selected" : ""}}>SME2</option> --}}
                                                </select>
                                            </div>
                                            <div class="form-group col-6">
                                                <label class="form-label">Plan Code</label>
                                                <input type="text" class="form-control" name="code" value="{{$plan->code}}" placeholder="Plan Code" required>
                                            </div>
                                            <div class="form-group col-6">
                                                <label class="form-label">Price</label>
                                                <input type="number" class="form-control" name="price" value="{{$plan->price}}" placeholder="Plan Price" required>
                                            </div>
                                        </div>
                                        <div class="form-group col-12 mb-0">
                                            <button class="btn btn-success w-100" type="submit">Edit Plan</button>
                                        </div>
                                    </form>
                                </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        {{ get_paginate($dataplans) }}
    </div>


<div class="modal fade" id="add-dataplan" tabindex="-1"  aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <div class="modal-header p-2">
            <h5 class="modal-title fw-bold">Create Data Plan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            
        </div>
        <div class="modal-body">
            <form action="{{route('admin.bills.dataplan.store')}}" enctype="multipart/form-data" method="post" class="row">
                @csrf
                <div class="form-group col-sm-12">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="name" placeholder="Plan Name" required>
                </div>
                <div class="form-group col-sm-6">
                    <label class="form-label">Network</label>
                    <select name="network_id" class="form-select" required>
                        @foreach($networks as $item)
                        <option value="{{$item->id}}">{{$item->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-sm-6">
                    <label class="form-label">Data Type</label>
                    <select name="service" class="form-select" required>
                        <option value="SME">SME</option>
                        <option value="CG">Corporate Gifting</option>
                        <option value="GIFTING">Gifting</option>
                        {{-- <option value="SME2">SME2</option> --}}
                    </select>
                </div>
                <div class="form-group col-sm-6">
                    <label class="form-label">Plan Code</label>
                    <input type="number" class="form-control" name="code" placeholder="Plan Code" required>
                </div>
                <div class="form-group col-sm-6">
                    <label class="form-label">Price</label>
                    <input type="number" class="form-control" name="price" placeholder="Plan Price" required>
                </div>
                <div class="form-group col-12 mb-0">
                    <button class="btn-success btn w-100" type="submit">Create Plan</button>
                </div>
            </form>
        </div>
        </div>
    </div>
</div>
@endsection

@push('script')
    
@endpush
