<div class="admin-testimonial-editor-grid">
    <section class="admin-testimonial-form-panel admin-section-card" aria-labelledby="testimonial-form-heading">
        <div class="admin-testimonials-panel-heading">
            <div><p class="admin-eyebrow">Manual entry</p><h2 id="testimonial-form-heading">{{ $isNew ? 'Review details' : 'Edit review details' }}</h2></div>
            <span class="admin-testimonials-status is-{{ $testimonial->status->value }}">{{ $testimonial->status->label() }}</span>
        </div>

        <form class="admin-testimonial-form" method="POST" enctype="multipart/form-data" action="{{ $isNew ? route('admin.testimonials.store') : route('admin.testimonials.update', $testimonial) }}" data-testimonial-form>
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless
            @if ($testimonial->source === \App\Enums\TestimonialSource::VerifiedCustomer)
                <p class="admin-testimonial-source-note"><i class="bi bi-patch-check" aria-hidden="true"></i> Verified customer submission. The display name is limited to the format the customer consented to; profile images are not collected for customer submissions.</p>
            @else
                <p class="admin-testimonial-source-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Source is fixed to Manual for entries created here. Google reviews are reserved for a future integration.</p>
            @endif

            <label>
                Customer display name
                <input type="text" name="customer_display_name" value="{{ old('customer_display_name', $testimonial->customer_display_name) }}" maxlength="120" autocomplete="off" required data-preview-name-input @if ($testimonial->source === \App\Enums\TestimonialSource::VerifiedCustomer) readonly @endif @error('customer_display_name') aria-invalid="true" @enderror>
                @error('customer_display_name')<small class="admin-testimonial-field-error">{{ $message }}</small>@enderror
            </label>

            <label>
                Review content
                <textarea name="review_content" rows="7" maxlength="3000" required data-preview-content-input @error('review_content') aria-invalid="true" @enderror>{{ old('review_content', $testimonial->review_content) }}</textarea>
                @error('review_content')<small class="admin-testimonial-field-error">{{ $message }}</small>@enderror
            </label>

            <div class="admin-testimonial-field-grid">
                <label>
                    Rating
                    <select name="rating" required data-preview-rating-input @error('rating') aria-invalid="true" @enderror>
                        <option value="">Select a rating</option>
                        @for ($rating = 5; $rating >= 1; $rating--)
                            <option value="{{ $rating }}" @selected((string) old('rating', $testimonial->rating) === (string) $rating)>{{ $rating }} out of 5</option>
                        @endfor
                    </select>
                    @error('rating')<small class="admin-testimonial-field-error">{{ $message }}</small>@enderror
                </label>
                <label>
                    Review date
                    <input type="date" name="review_date" value="{{ old('review_date', $testimonial->review_date?->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required data-preview-date-input @error('review_date') aria-invalid="true" @enderror>
                    @error('review_date')<small class="admin-testimonial-field-error">{{ $message }}</small>@enderror
                </label>
            </div>

            <div class="admin-testimonial-field-grid">
                <label>
                    Display order
                    <input type="number" name="display_order" min="0" max="999999" value="{{ old('display_order', $testimonial->display_order) }}" required>
                    <small>Lower numbers appear first within the featured or standard group.</small>
                    @error('display_order')<small class="admin-testimonial-field-error">{{ $message }}</small>@enderror
                </label>
                <label class="admin-testimonial-feature-toggle">
                    <span>Homepage placement</span>
                    <span class="admin-testimonial-checkbox">
                        <input type="hidden" name="is_featured" value="0">
                        <input type="checkbox" name="is_featured" value="1" @checked((bool) old('is_featured', $testimonial->is_featured))>
                        Feature this testimonial
                    </span>
                </label>
            </div>

            @unless ($testimonial->source === \App\Enums\TestimonialSource::VerifiedCustomer)
                <label class="admin-testimonial-image-field">
                    Optional profile image
                    <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-preview-image-input @error('profile_image') aria-invalid="true" @enderror>
                    <small>JPG, PNG, or WebP; up to 2 MB; square or landscape, 80–1600 px in each dimension.</small>
                    @error('profile_image')<small class="admin-testimonial-field-error">{{ $message }}</small>@enderror
                </label>
                @if ($testimonial->profile_image_path)
                    <label class="admin-testimonial-remove-image">
                        <input type="checkbox" name="remove_profile_image" value="1" data-preview-remove-image>
                        Remove current profile image
                    </label>
                @endif
            @endunless

            <div class="admin-testimonial-form-footer">
                <span>New entries are saved as drafts and are not public until published.</span>
                <button class="admin-primary-link" type="submit"><i class="bi bi-save" aria-hidden="true"></i> {{ $isNew ? 'Save draft' : 'Save changes' }}</button>
            </div>
        </form>
    </section>

    <aside class="admin-testimonial-preview-panel admin-section-card" aria-labelledby="testimonial-preview-heading">
        <div class="admin-testimonials-panel-heading">
            <div><p class="admin-eyebrow">Live preview</p><h2 id="testimonial-preview-heading">Homepage card</h2></div>
        </div>
        <p class="admin-testimonial-preview-note">Preview only. Save and publish to display this review publicly.</p>
        @include('components.testimonial-card', ['testimonial' => $testimonial, 'isPreview' => true])
    </aside>
</div>
