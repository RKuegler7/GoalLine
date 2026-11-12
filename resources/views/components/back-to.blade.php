{{-- Names this page's parent for the top bar's Back (used when the member didn't come from inside GoalLine). Renders
     nothing: the arrow and label live in the top bar. --}}
@props(['href', 'label'])

@php(request()->attributes->set('page_back', ['url' => $href, 'label' => $label]))
