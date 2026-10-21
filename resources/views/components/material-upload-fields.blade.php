{{-- File and note fields for uploading a plan, course, or program. --}}
<fieldset class="rounded-xl border border-dashed border-line bg-ivory/60 p-5">
    <legend class="px-1 text-sm font-medium text-ink/80">Following a plan, course, or program? <span class="font-normal text-ink/50">(optional)</span></legend>
    <p class="text-sm text-ink/60">
        Upload your study plan, training program, or meal plan and your roadmap will be built around it. PDF, PNG, or JPG, up to 10 MB.
    </p>

    <label for="material" class="sr-only">Plan file</label>
    <input id="material" name="material" type="file" accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
        class="mt-3 block w-full text-sm text-ink/70 file:mr-4 file:rounded-lg file:border-0 file:bg-mint file:px-4 file:py-2 file:text-sm file:font-semibold file:text-club hover:file:bg-grass/20">
    @error('material')
        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
    @enderror

    <label for="material_note" class="mt-4 mb-1.5 block text-sm font-medium text-ink/80">What is it? <span class="font-normal text-ink/50">(optional)</span></label>
    <input id="material_note" name="material_note" value="{{ old('material_note') }}" maxlength="200" placeholder="Becker FAR study plan, 12-week schedule"
        class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none transition placeholder:text-ink/40 focus:border-club focus:ring-4 focus:ring-club/10">
    @error('material_note')
        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
    @enderror
</fieldset>
