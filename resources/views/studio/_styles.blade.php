{{-- Scoped in studio/audits.astro; shared by the studio pages here. --}}
<style>
    .studio-input { display: block; width: 100%; box-sizing: border-box; padding: 0.75rem 1rem; border: 1px solid var(--color-border); border-radius: 3px; background-color: var(--color-bg-surface); font-family: var(--font-body); font-size: 14px; color: var(--color-text-primary); outline: none; transition: border-color 0.15s; }
    .studio-input:focus { border-color: var(--color-accent); }
    .studio-btn-primary, .studio-btn-secondary { display: inline-block; padding: 0.625rem 1.25rem; border-radius: 3px; font-family: var(--font-body); font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.08em; cursor: pointer; transition: all 0.15s; border: 1px solid; text-decoration: none; }
    .studio-btn-primary { background: var(--color-accent); border-color: var(--color-accent); color: #fff; }
    .studio-btn-primary:hover { background: var(--color-accent-hover); border-color: var(--color-accent-hover); color: #fff; }
    .studio-btn-secondary { background: transparent; border-color: var(--color-border); color: var(--color-text-secondary); }
    .studio-btn-secondary:hover { border-color: var(--color-border-hover); color: var(--color-text-primary); }
    .studio-row td { padding: 0.875rem 1rem; border-top: 1px solid var(--color-border); vertical-align: middle; }
    .studio-row:hover { background: var(--color-bg-muted); }
    .studio-status-badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 3px; background: var(--color-bg-muted); color: var(--color-text-secondary); font-family: var(--font-body); font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; }
    .studio-status-badge[data-status="submitted"] { background: var(--color-engineering-bg); color: var(--color-engineering); }
    .studio-status-badge[data-status="ready_to_send"] { background: #E8F4EA; color: #2d7d46; }
    .studio-status-badge[data-status="sent"] { background: var(--color-bg-muted); color: var(--color-text-tertiary); }
    .studio-status-badge[data-status="deleted"] { background: #FBE9E7; color: #6B6560; text-decoration: line-through; }
    .deleted-row { opacity: 0.5; }
    .studio-flash { margin-bottom: 1.5rem; padding: 0.75rem 1rem; border: 1px solid var(--color-border); border-radius: 3px; background: var(--color-bg-surface); font-size: 13px; }
    .studio-flash.is-error { border-color: var(--color-destructive); color: var(--color-destructive); background: var(--color-error-bg); }
</style>
