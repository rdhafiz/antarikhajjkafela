<x-site.block :style="$style" :section="$section">
    <div class="grid items-start gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
        <x-site.block-heading :c="$c" align="left" class="lg:sticky lg:top-28" />

        <x-site.form-shell :section="$section" :submit-label="$c['submit_label'] ?: __('Send message')" :success="$c['success'] ?: __('Thank you! We will contact you shortly.')">
            @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'name', 'label' => __('Full name'), 'type' => 'text', 'required' => true, 'autocomplete' => 'name'])
            @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'phone', 'label' => __('Mobile number'), 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel'])
            @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'email', 'label' => __('Email'), 'type' => 'email', 'required' => false, 'autocomplete' => 'email'])
            @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'subject', 'label' => __('Subject'), 'type' => 'text', 'required' => false])
            @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'message', 'label' => __('Message'), 'type' => 'textarea', 'required' => true])
        </x-site.form-shell>
    </div>
</x-site.block>
