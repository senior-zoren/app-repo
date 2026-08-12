@extends('layouts.app')

@section('title', 'Applications Dashboard')

@section('content')
    {{-- $app is the raw slug string passed by Route::view() --}}
    <livewire:release :app="$app" />
@endsection