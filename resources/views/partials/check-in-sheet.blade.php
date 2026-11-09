{{-- The "Check in" sheet: today's list, loaded over any page. Its own region, so checking off here refreshes only it. --}}
<x-todays-gameplan :gameplan="$gameplan" return="today" region="checkin-sheet" anchor="checkin-gameplan" class="border-0" />
