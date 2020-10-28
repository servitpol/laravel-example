<!-- Пример оформелния view внутренней страницы сервиса мониторинга сайтов -->

@extends('layouts.app')
@push('deadline')
<script src="{{ asset('js/projects.js') }}?v={{ filectime('./js/projects.js') }}"></script>
@endpush
@section('content')

@foreach($domains as $key => $site)
@php
$opt .= '<option value="' . $site->id . '">' . $site->site. '</option>';
@endphp

@endforeach
<div class="wrapper">
    @include('sidebar')
    <div class="container" id="content">
        @if(session('new_status'))
        <div class="alert alert-success">
            <span>{{ session('new_status') }}</span>
        </div>
        @endif
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Проверить сайт на индексацию</div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Введите домен</label>
                            <input type="text" class="form-control" name="pro_name" id="one_site" placeholder="Введите url или чистый домен, скрипт сам добавит site:" required="required" />
                        </div>
                    </div>
                    <div class="card-footer text-muted">
                        <button type="submit" class="btn btn-default check_one">Проверить</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Результат проверки</div>
                    <div class="card-body">
                        <pre style="height: 130px;" id="result_one_check"></pre>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Новый проект</div>
                    <form method="POST" action="{{route('add_new_project')}}">
                        {{ csrf_field() }}
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Название</label>
                                        <input type="text" class="form-control" name="pro_name" placeholder="Введите название проекта" required="required" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label>Основной сайт</label><br>
                                    <select class="selectpicker" name="main_site" data-live-search="true">
                                        {!!$opt!!}
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-muted"><button type="submit" class="btn btn-primary">Добавить</button></div>
                    </form>
                </div>
            </div>
        </div>

        @if(isset($projects))
        <div class="card">
            <div class="card-header">Проекты</div>
            <table class="table table-responsive-sm table-striped tbaccs">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Название</th>
                        <th>Основной сайт</th>
                        <th>Сущность</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $i = 1;
                    @endphp
                    @foreach($projects as $project)
                    <tr>
                        <td>{{$i++}}</td>
                        <td>{{$project->name}}</td>
                        <td>{{$project->site}}</td>
                        <td>{{$project->name_field}}</td>
                        <td>
                            <div class="wait"></div>
                            <a href="" data-target="#logModal{{$project->id}}" class="btn btn-sm btn-success prolog" data-toggle="modal" data-pro_id="{{$project->id}}"><i class="fas fa-archive"> Log</i></a>
                            <a href="" data-target="#newmyModal" class="btn btn-sm btn-info pro_edit" data-toggle="modal" data-pro_id="{{$project->id}}" data-status="1"><i class="fas fa-pen"> Редактировать</i></a>
                            <a href="#" data-pro_id="{{$project->id}}" class="btn btn-sm btn-danger pro_delete">Удалить</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <div id="newmyModal" class="modal fade">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="card-header pro_name_header">Редактирование проекта </div>
                <form method="POST" action="{{route('update_project')}}">
                    {{ csrf_field() }}
                    <input type="text" name="pro_id" id="pro_id" value="" class="d-none">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Изменить название проекта</label>
                                    <input type="text" class="form-control pro_name" name="pro_name" value="" required="required" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Добавить в проект сайты</label><br>
                                    <select class="selectpicker" name="sites[]" multiple data-live-search="true">
                                        {!!$opt!!}
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Привязать сущность</label><br>
                                    <select class="pro_field" name="pro_field" >
                                        <option value="0">Нету</option>
                                        @foreach($fields as $filed)                         
                                        <option value="{{$filed->id}}">{{$filed->name_field}}</option>                         
                                        @endforeach 
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <table class="table table-responsive-sm table-hover" id="myTable">
                                    <thead>
                                        <tr class="sticky-top">
                                            <th class="sticky-top">Домен</th>
                                            <th class="sticky-top">Основной</th>
                                            <th class="sticky-top">Действие</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer text-muted"><button type="submit" class="btn btn-primary">Обновить</button></div>
                </form>
            </div>
        </div>
    </div>

    @foreach($projects as $pro)
    <div id="logModal{{$pro->id}}" class="modal fade">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="card-header">Статистика изменений в выдаче по проекту "{{$pro->name}}"</div>

                <input type="text" name="pro_id" value="{{$pro->id}}" class="d-none">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <table class="table table-responsive-sm table-hover" id="myTable">
                                <thead>
                                    <tr class="sticky-top">
                                        <th class="sticky-top">Проект</th>
                                        <th class="sticky-top">Домен</th>
                                        <th class="sticky-top">Дата</th>
                                        <th class="sticky-top">Изменения</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @if(!empty($log))
                                    @foreach($log as $key)
                                    <tr>
                                        <td>{{$key['project']}}</td>
                                        <td>{{$key['domain']}}</td>
                                        @php
                                        $date = new DateTime($key['date']);
                                        $date->add(new DateInterval('P0Y0M0DT3H0M0S'));
                                        @endphp
                                        <td>{{$date->format('Y-m-d H:i:s')}}</td>
                                        @if($key['index'] == 1)
                                        <td>Есть в выдаче</td>
                                        @else
                                        <td>Нет в выдаче</td>
                                        @endif
                                    </tr>
                                    @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
    @endsection
