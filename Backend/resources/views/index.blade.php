@extends('layout')

@section('content')

    @include('navbar')
    <div class="wrapper">
        @include('header')
        <div class="main main-raised">
            @include('sections.about')
            @include('sections.features')
            @include('sections.modules')
            {{-- @include('sections.screamshot') --}}
            @include('sections.advantages')
            {{-- @include('sections.reviews') --}}
            @include('sections.plans')
        </div>
        @include('footer')
    </div>

@endsection
