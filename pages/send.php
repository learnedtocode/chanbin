<?php

if (!run_hooks('csrf_validate', $_POST['csrf'] ?? null)) {
	fail(400, 'Page expired', true);
}

fail(400, 'Not implemented yet');
