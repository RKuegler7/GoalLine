@extends('errors.layout')

@section('code', '500')
@section('title', 'Something slipped on our end')
@section('message', 'It’s not you. We’ve been told and we’re on it. Try again in a moment.')

@section('actions')
    <a href="{{ request()->isMethod('GET') ? request()->fullUrl() : '/' }}" class="inline-flex min-h-11 items-center rounded-full border border-club/30 bg-white px-5 text-sm font-semibold text-club hover:border-club">Try again</a>
@endsection
