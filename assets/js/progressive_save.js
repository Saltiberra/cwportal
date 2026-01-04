/**
 * Progressive Data Saving to Database
 * Functions to handle AJAX calls to endpoints for progressive data storage
 */

/**
 * Get current report ID from URL or session
 * @returns {number|null}
 */
function getReportId() {
    const params = new URLSearchParams(window.location.search);
    return params.get('report_id') ? parseInt(params.get('report_id')) : null;
}

/**
 * Save Module to Database Progressively
 * @param {Object} moduleData - Module data to save
 * @returns {Promise}
 */
async function saveModuleToDb(moduleData) {
    const reportId = getReportId();
    if (!reportId) {
        console.error('No report ID found');
        throw new Error('Cannot save: No active report');
    }

    const payload = {
        report_id: reportId,
        ...moduleData
    };

    const response = await fetch('ajax/save_module.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error saving module');
    }

    return result;
}

/**
 * Save Inverter to Database Progressively
 * @param {Object} inverterData - Inverter data to save
 * @returns {Promise}
 */
async function saveInverterToDb(inverterData) {
    const reportId = getReportId();
    if (!reportId) {
        console.error('No report ID found');
        throw new Error('Cannot save: No active report');
    }

    const payload = {
        report_id: reportId,
        ...inverterData
    };

    const response = await fetch('ajax/save_inverter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error saving inverter');
    }

    return result;
}

/**
 * Save Layout to Database Progressively
 * @param {Object} layoutData - Layout data to save
 * @returns {Promise}
 */
async function saveLayoutToDb(layoutData) {
    const reportId = getReportId();
    if (!reportId) {
        console.error('No report ID found');
        throw new Error('Cannot save: No active report');
    }

    const payload = {
        report_id: reportId,
        ...layoutData
    };

    const response = await fetch('ajax/save_layout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error saving layout');
    }

    return result;
}

/**
 * Save Protection Data to Database
 * @param {Object} protectionData - Protection data to save
 * @returns {Promise}
 */
async function saveProtectionToDb(protectionData) {
    const reportId = getReportId();
    if (!reportId) {
        console.error('No report ID found');
        throw new Error('Cannot save: No active report');
    }

    const payload = {
        report_id: reportId,
        ...protectionData
    };

    const response = await fetch('ajax/save_protection.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error saving protection data');
    }

    return result;
}

/**
 * Save String Measurement to Database
 * @param {Object} measurementData - Measurement data to save
 * @returns {Promise}
 */
async function saveStringMeasurementToDb(measurementData) {
    const reportId = getReportId();
    if (!reportId) {
        console.error('No report ID found');
        throw new Error('Cannot save: No active report');
    }

    const payload = {
        report_id: reportId,
        ...measurementData
    };

    const response = await fetch('ajax/save_string_measurement.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error saving string measurement');
    }

    return result;
}

/**
 * Save Communication Device to Database
 * @param {Object} commData - Communication data to save
 * @returns {Promise}
 */
async function saveCommunicationToDb(commData) {
    const reportId = getReportId();
    if (!reportId) {
        console.error('No report ID found');
        throw new Error('Cannot save: No active report');
    }

    const payload = {
        report_id: reportId,
        ...commData
    };

    const response = await fetch('ajax/save_communication.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error saving communication');
    }

    return result;
}

/**
 * Save Telemetry Meter to Database
 * @param {Object} meterData - Telemetry meter data to save
 * @returns {Promise}
 */
async function saveTelemetryMeterToDb(meterData) {
    const reportId = getReportId();
    if (!reportId) {
        console.error('No report ID found');
        throw new Error('Cannot save: No active report');
    }

    const payload = {
        report_id: reportId,
        ...meterData
    };

    const response = await fetch('ajax/save_telemetry_meter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error saving telemetry meter');
    }

    return result;
}

/**
 * Save Energy Meter to Database
 * @param {Object} meterData - Energy meter data to save
 * @returns {Promise}
 */
async function saveEnergyMeterToDb(meterData) {
    const reportId = getReportId();
    if (!reportId) {
        console.error('No report ID found');
        throw new Error('Cannot save: No active report');
    }

    const payload = {
        report_id: reportId,
        ...meterData
    };

    const response = await fetch('ajax/save_energy_meter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error saving energy meter');
    }

    return result;
}

/**
 * Save Punch List Item to Database
 * @param {Object} punchData - Punch list item data to save
 * @returns {Promise}
 */
async function savePunchItemToDb(punchData) {
    const reportId = getReportId();
    if (!reportId) {
        console.error('No report ID found');
        throw new Error('Cannot save: No active report');
    }

    const payload = {
        report_id: reportId,
        ...punchData
    };

    const response = await fetch('ajax/save_punch_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error saving punch item');
    }

    return result;
}

/**
 * Save Note to Database
 * @param {Object} noteData - Note data to save
 * @returns {Promise}
 */
async function saveNoteToDb(noteData) {
    const reportId = getReportId();
    if (!reportId) {
        console.error('No report ID found');
        throw new Error('Cannot save: No active report');
    }

    const payload = {
        report_id: reportId,
        ...noteData
    };

    const response = await fetch('ajax/save_notes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error saving note');
    }

    return result;
}

/**
 * Delete Item from Database
 * @param {string} tableName - Name of the table
 * @param {number} itemId - ID of the item to delete
 * @returns {Promise}
 */
async function deleteItemFromDb(tableName, itemId) {
    const payload = {
        table: tableName,
        id: itemId
    };

    const response = await fetch('ajax/delete_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Unknown error deleting item');
    }

    return result;
}

/**
 * Load Report Data from Database
 * Called when editing an existing report
 * @param {number} reportId - ID of the report to load
 * @returns {Promise}
 */
async function loadReportDataFromDb(reportId) {
    if (!reportId) {
        return null;
    }

    try {
        // This is called from the PHP side via window.reportData
        // But we can also fetch if needed
        console.log('Loading report data for report ID:', reportId);
        return window.reportData || null;
    } catch (error) {
        console.error('Error loading report data:', error);
        throw error;
    }
}

/**
 * Populate Form with Loaded Data
 * Called after loading report data from database
 * @param {Object} reportData - Report data loaded from database
 */
function populateFormWithData(reportData) {
    if (!reportData) return;

    // Populate modules
    if (reportData.modules && Array.isArray(reportData.modules)) {
        modulesList = [];
        reportData.modules.forEach(module => {
            modulesList.push({
                id: module.id,
                brand_id: module.pv_module_brand_id,
                brand_name: module.brand_name,
                model_id: module.pv_module_model_id,
                model_name: module.model_name,
                quantity: module.quantity,
                status: module.deployment_status,
                power_rating: module.power_rating
            });
        });
        updateModulesTable();
    }

    // Populate inverters
    if (reportData.inverters && Array.isArray(reportData.inverters)) {
        invertersList = [];
        reportData.inverters.forEach(inverter => {
            invertersList.push({
                id: inverter.id,
                brand_id: inverter.inverter_brand_id,
                brand_name: inverter.brand_name,
                model_id: inverter.inverter_model_id,
                model_name: inverter.model_name,
                quantity: inverter.quantity,
                phase_type: inverter.phase_type,
                power_rating: inverter.power_rating
            });
        });
        if (typeof updateInvertersTable === 'function') {
            updateInvertersTable();
        }
    }

    // Populate string measurements
    if (reportData.string_measurements && Array.isArray(reportData.string_measurements)) {
        if (typeof populateStringMeasurements === 'function') {
            populateStringMeasurements(reportData.string_measurements);
        }
    }

    console.log('✅ Form populated with loaded data');
}
