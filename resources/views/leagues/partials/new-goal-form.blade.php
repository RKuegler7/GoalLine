{{-- Set a goal right on a league's join page, then come back to join with it. --}}
<form method="POST" action="{{ route('goals.store') }}" class="mt-5 space-y-4">
    @csrf
    <input type="hidden" name="from" value="league">
    <x-form.input name="title" label="Your goal" maxlength="120" placeholder="Pass FAR by December 15" required />
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="join-category" class="mb-1.5 block text-sm font-medium text-ink/80">Kind of goal</label>
            <select id="join-category" name="category" required class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                @foreach (App\GoalCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $league->category?->value) === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </select>
        </div>
        <x-form.input name="target_date" label="Done by" type="date" :value="$league->ends_on->format('Y-m-d')" required />
    </div>
    <x-form.button class="sm:w-auto sm:px-8">Set goal</x-form.button>
</form>
