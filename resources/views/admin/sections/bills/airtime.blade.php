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
    ], 'active' => __("Setup Airtime")])
@endsection

@section('content')
    <div class="table-area">
        <div class="table-wrapper">
            <div class="table-header">
                <h5 class="title">{{ __("Airtime Networks") }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="datatable">
                    <thead>
                        <tr>
                        <th>S/N</th>
                        <th>Name</th>
                        <th>Minimum</th>
                        <th>Status</th>
                        <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($networks as $key => $network)
                        <tr>
                            <td>{{$key +1}}</td>
                            <td>{{$network->name}}</td>
                            <td>{{($network->minimum)}}</td>
                            <td><span class="badge @if($network->airtime == 1)bg-success @else bg-danger @endif">@if($network->airtime == 1)active @else disabled @endif </span></td>
                            <td>
                                <button class="btn btn--base edit-modal-button btn-sm" type="button"  data-bs-toggle="modal" data-bs-target="#edit_modal-{{$network->id}}">
                                    <i class="fa fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        {{-- edit modals --}}
                        <div class="modal fade" id="edit_modal-{{$network->id}}" tabindex="-1"  aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                <div class="modal-header p-2">
                                    <h5 class="modal-title fw-bold">Edit Network</h5>
                                </div>
                                <div class="modal-body pt-0">
                                    <form action="{{route('admin.bills.airtime.update',$network->id)}}" enctype="multipart/form-data" method="post">
                                        @csrf
                                        <div class="form-group col-12 mt-0">
                                            <label class="form-label">Minimum Account</label>
                                            <input type="number" class="form-control" name="minimum" placeholder="minimum airtime" value="{{$network->minimum}}" required>
                                        </div>
                                        <div class="row">
                                        <div class="form-group col-6">
                                            <label class="form-label">Status</label>
                                            <select name="airtime" class="form-select form-control" required>
                                                 <option value="1" {{$network->airtime == '1' ? "selected" : ""}}>Enabled</option>
                                                 <option value="2" {{$network->airtime == '2' ? "selected" : ""}}>Disabled</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-6">
                                            <label class="form-label">API Code</label>
                                            <input type="text" class="form-control" value="{{$network->code}}" name="code" placeholder="Plan Code" required>
                                        </div>
                                        </div>
                                        <div class="form-group mb-0">
                                            <button class="btn-success w-100 btn" type="submit">Update</button>
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
        {{ get_paginate($networks) }}
    </div>

@endsection

@push('script')
    
@endpush
