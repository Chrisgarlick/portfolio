{{--
    The free site-audit tool, ported from components/tools/SiteAuditTool.astro.

    Behaviour is in resources/js/site/site-audit.js: it queues a run, then
    polls for the result, because the audit runs on the queue rather than
    inside the request (see SiteAuditController). The markup holds nothing
    per-visitor, so the tool page stays page-cacheable.

    Styles were scoped to the Astro component and stay with it here, inline,
    because they only matter on this one page.
--}}
<div class="audit-tool" data-site-audit data-endpoint="{{ route('site-audit.store', [], false) }}">
  <!-- Input form -->
  <form id="audit-form" class="audit-form" novalidate>
    <div class="audit-input-row">
      <div class="audit-input-wrap">
        <label for="audit-url" class="sr-only">Website address</label>
        <input
          type="url"
          id="audit-url"
          name="url"
          placeholder="https://example.com"
          required
          class="audit-input"
          autocomplete="url"
        />
      </div>
      <button type="submit" class="audit-submit" id="audit-submit">
        Run audit
      </button>
    </div>
    <p class="audit-hint">Enter any URL to get a free site health report.</p>
    <p class="audit-error hidden" id="audit-error-validation">Please enter a valid URL (e.g. https://example.com)</p>

    <!-- Segmentation field — optional, used to route post-audit follow-up to the right case study / service page -->
    <div class="audit-segmentation">
      <label for="audit-task" class="audit-segmentation-label">
        What manual task do you wish you never had to do again?
        <span class="audit-segmentation-optional">(optional, this is what we'll talk through if you book a call)</span>
      </label>
      <input
        type="text"
        id="audit-task"
        name="task"
        maxlength="200"
        placeholder="e.g. client intake, monthly reports, contract review…"
        class="audit-input audit-segmentation-input"
        autocomplete="off"
      />
    </div>
  </form>

  <!-- Loading -->
  <div id="audit-loading" class="audit-loading hidden">
    <div class="audit-spinner"></div>
    <p>Auditing site… this usually takes 15–30 seconds.</p>
  </div>

  <!-- Error -->
  <div id="audit-error" class="audit-message audit-message--error hidden">
    <p id="audit-error-text">Something went wrong. Please try again.</p>
  </div>

  <!-- Results -->
  <div id="audit-results" class="hidden">
    <div class="audit-results-header">
      <h3 class="audit-results-title">Results</h3>
      <p class="audit-results-url" id="audit-results-url"></p>
    </div>

    <div class="audit-scores">
      <div class="audit-score-card" id="score-overall">
        <div class="audit-score-number" data-score>—</div>
        <div class="audit-score-bar"><div class="audit-score-bar-fill" data-bar></div></div>
        <div class="audit-score-label">Overall</div>
      </div>
      <div class="audit-score-card" id="score-seo">
        <div class="audit-score-number" data-score>—</div>
        <div class="audit-score-bar"><div class="audit-score-bar-fill" data-bar></div></div>
        <div class="audit-score-label">SEO</div>
      </div>
      <div class="audit-score-card" id="score-accessibility">
        <div class="audit-score-number" data-score>—</div>
        <div class="audit-score-bar"><div class="audit-score-bar-fill" data-bar></div></div>
        <div class="audit-score-label">Accessibility</div>
      </div>
      <div class="audit-score-card" id="score-performance">
        <div class="audit-score-number" data-score>—</div>
        <div class="audit-score-bar"><div class="audit-score-bar-fill" data-bar></div></div>
        <div class="audit-score-label">Performance</div>
      </div>
    </div>

    <!-- Email gate / CTA -->
    <div class="audit-cta">
      <div class="audit-cta-inner">
        <h4 class="audit-cta-heading">Want the full breakdown?</h4>
        <p class="audit-cta-body">
          I'll run a deep multi-page audit on your site and record a personalised walkthrough of the findings. No pitch, just value.
        </p>
        <a href="/contact" class="audit-cta-button" id="audit-cta-link">
          Get your free walkthrough
        </a>
      </div>
    </div>
  </div>

</div>

<style>
  /* The live component's scoped `.hidden !important`: these rules come after
     Tailwind's, so without it `.audit-loading { display: flex }` would win. */
  .audit-tool .hidden {
    display: none !important;
  }


  .audit-tool {
    width: 100%;
  }

  .audit-form {
    width: 100%;
  }

  .audit-input-row {
    display: flex;
    gap: 0.75rem;
    align-items: stretch;
  }

  @media (max-width: 640px) {
    .audit-input-row {
      flex-direction: column;
    }
  }

  .audit-input-wrap {
    flex: 1;
  }

  .audit-input {
    display: block;
    width: 100%;
    box-sizing: border-box;
    padding: 0.75rem 1rem;
    border: 1px solid var(--color-field-border);
    border-radius: 3px;
    background-color: var(--color-bg-surface);
    font-family: var(--font-body);
    font-size: 15px;
    color: var(--color-text-primary);
    transition: border-color 0.2s;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
    height: 100%;
  }

  .audit-input::placeholder {
    color: var(--color-text-tertiary);
  }

  .audit-input:focus {
    border-color: var(--color-accent);
  }

  .audit-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 2rem;
    border: 1px solid var(--color-accent);
    border-radius: 3px;
    background-color: var(--color-accent);
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #fff;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
  }

  .audit-submit:hover {
    background-color: var(--color-accent-hover);
    border-color: var(--color-accent-hover);
    transform: translateY(-1px);
  }

  .audit-submit:disabled {
    opacity: 0.5;
    transform: none;
    cursor: default;
  }

  .audit-hint {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--color-text-tertiary);
  }

  .audit-segmentation {
    margin-top: 1.25rem;
  }

  .audit-segmentation-label {
    display: block;
    margin-bottom: 0.5rem;
    font-family: var(--font-body);
    font-size: 0.8125rem;
    color: var(--color-text-secondary);
    line-height: 1.5;
  }

  .audit-segmentation-optional {
    color: var(--color-text-tertiary);
    font-style: italic;
  }

  .audit-segmentation-input {
    padding-top: 0.625rem;
    padding-bottom: 0.625rem;
    font-size: 14px;
  }

  .audit-error {
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--color-destructive);
  }

  /* Loading */
  .audit-loading {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 2rem;
    padding: 1.5rem;
    border: 1px solid var(--color-border);
    border-radius: 3px;
    background-color: var(--color-bg-surface);
  }

  .audit-loading p {
    font-size: 0.9375rem;
    color: var(--color-text-secondary);
  }

  .audit-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid var(--color-border);
    border-top-color: var(--color-accent);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    flex-shrink: 0;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  /* Error message */
  .audit-message {
    margin-top: 2rem;
    padding: 1.5rem;
    border-radius: 3px;
  }

  .audit-message--error {
    border: 1px solid var(--color-destructive);
    background-color: var(--color-accent-light);
  }

  .audit-message--error p {
    font-size: 0.9375rem;
    color: var(--color-text-primary);
  }

  /* Results */
  .audit-results-header {
    margin-top: 2rem;
    margin-bottom: 1.5rem;
  }

  .audit-results-title {
    font-family: var(--font-display);
    font-size: 1.5rem;
    color: var(--color-text-primary);
    margin-bottom: 0.25rem;
  }

  .audit-results-url {
    font-family: var(--font-body);
    font-size: 0.8125rem;
    color: var(--color-text-tertiary);
    word-break: break-all;
  }

  .audit-scores {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }

  @media (min-width: 640px) {
    .audit-scores {
      grid-template-columns: repeat(4, 1fr);
    }
  }

  .audit-score-card {
    border: 1px solid var(--color-border);
    border-radius: 3px;
    background-color: var(--color-bg-surface);
    padding: 1.25rem;
    text-align: center;
  }

  .audit-score-number {
    font-family: var(--font-display);
    font-size: 2.5rem;
    line-height: 1;
    color: var(--color-text-primary);
    margin-bottom: 0.75rem;
  }

  .audit-score-bar {
    height: 4px;
    background-color: var(--color-bg-muted);
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 0.75rem;
  }

  .audit-score-bar-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    width: 0%;
  }

  .audit-score-label {
    font-family: var(--font-body);
    font-size: 0.6875rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--color-text-tertiary);
  }

  /* CTA */
  .audit-cta {
    margin-top: 2rem;
  }

  .audit-cta-inner {
    border: 1px solid var(--color-border);
    border-radius: 3px;
    background-color: var(--color-bg-dark);
    padding: 2rem;
    text-align: center;
  }

  .audit-cta-heading {
    font-family: var(--font-display);
    font-size: 1.25rem;
    color: #fff;
    margin-bottom: 0.5rem;
  }

  .audit-cta-body {
    font-size: 0.9375rem;
    line-height: 1.75;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 1.5rem;
    max-width: 440px;
    margin-left: auto;
    margin-right: auto;
  }

  .audit-cta-button {
    display: inline-block;
    padding: 0.75rem 2rem;
    border: 1px solid #fff;
    border-radius: 3px;
    background-color: #fff;
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-bg-dark);
    text-decoration: none;
    transition: all 0.2s;
  }

  .audit-cta-button:hover {
    background-color: rgba(255, 255, 255, 0.9);
  }
</style>
