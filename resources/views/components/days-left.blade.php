@props(['days'])

@if ($days > 1)
    {{ $days }} days to go
@elseif ($days === 1)
    1 day to go
@elseif ($days === 0)
    Due today
@else
    Date passed
@endif
