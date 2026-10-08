/*
 * The public site's JavaScript. Small on purpose: every page is static HTML
 * served from the page cache, and each module here only enhances markup that
 * already works, or at least reads correctly, without it. Page-specific
 * modules look for their own markup and return immediately when it is absent.
 */

import { initNav } from './site/nav';
import { initCookieConsent } from './site/cookie-consent';
import { initFilters } from './site/filters';
import { initForms } from './site/forms';
import { initResourceGate } from './site/resource-gate';
import { initAuditForm } from './site/audit-form';
import { initDataDelete } from './site/data-delete';
import { initDiagnostic } from './site/diagnostic';
import { initSiteAudit } from './site/site-audit';
import { initTopicFilter } from './site/topic-filter';
import { initReadingProgress } from './site/reading-progress';

initNav();
initCookieConsent();
initFilters();
initForms();
initResourceGate();
initAuditForm();
initDataDelete();
initDiagnostic();
initSiteAudit();
initTopicFilter();
initReadingProgress();
