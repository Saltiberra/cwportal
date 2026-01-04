<?php
/**
 * AJAX Endpoint for Datasheet Search
 * 
 * This file serves as an entry point for datasheet search requests
 */

// Database connection
require_once '../config/database.php';
// Uncomment if functions.php is needed
// require_once '../config/functions.php';

// Set flag to indicate this is an AJAX request
define('AJAX_REQUEST', true);

// Include the search service
require_once 'search_datasheets.php';