@extends('errors.layout')

@section('title', 'Page expired')
@section('eyebrow', '419 / Page Expired')
@section('code', '419')
@section('heading', 'Page expired')
@section('message')
    Your session may have timed out or the form token expired. Please refresh and try again.
@endsection
@section('actions')
    <button class="btn btn-primary" type="button" onclick="location.reload()">
        <i data-lucide="rotate-cw"></i>
        <span>Try again</span>
    </button>
    <a class="btn btn-ghost" href="{{ route('home') }}">
        <i data-lucide="home"></i>
        <span>Go to home</span>
    </a>
@endsection
