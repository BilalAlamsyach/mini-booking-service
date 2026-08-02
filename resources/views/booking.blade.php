@extends('layouts.app')

{{--
    Seluruh tahap alur dimuat sekaligus, lalu ditampilkan bergantian oleh
    app.js. Dipecah menjadi partial agar tiap berkas tetap pendek dan mudah
    ditelusuri.
--}}
@section('content')
    @include('partials.pending')

    @include('partials.login')
    @include('partials.search')
    @include('partials.seats')
    @include('partials.summary')
    @include('partials.result')
@endsection
