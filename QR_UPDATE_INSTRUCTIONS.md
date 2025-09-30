# QR Code Update Instructions

## Problem Solved

The existing devices in the database still have old Google Charts API QR code links, which are deprecated and unreliable. This update fixes that issue.

## Solution

A new update script has been created to replace all old QR codes with new, reliable ones.

## How to Update Existing QR Codes

### Method 1: Using the Web Interface (Recommended)

1. **Access the Update Page:**

   - Go to the Dashboard (index.php) or Inventory page
   - Click the "Update QR Codes" button in the header
   - Or navigate directly to: `update_existing_qr_codes.php`

2. **Run the Update:**

   - Click "Update All QR Codes" button
   - The system will automatically:
     - Scan all existing devices
     - Identify devices with old Google Charts API links
     - Generate new reliable QR codes
     - Update the database

3. **View Results:**
   - See how many devices were updated
   - View the status of each device's QR code
   - Verify that all devices now have proper QR codes

### Method 2: Direct Database Update (Advanced)

If you prefer to run the update directly via command line or need to update a large number of devices:

```php
// The update logic is in update_existing_qr_codes.php
// You can extract the PHP code and run it directly
```

## What Gets Updated

### Devices That Will Be Updated:

- ✅ Devices with old Google Charts API links (`chart.googleapis.com`)
- ✅ Devices with no QR codes (empty `qr_code` field)
- ✅ Devices with invalid QR code formats

### Devices That Won't Be Updated:

- ✅ Devices with new QR codes (data URIs or reliable URLs)
- ✅ Devices with properly formatted QR codes

## New QR Code Features

### Automatic Generation:

- **New devices** automatically get QR codes when added
- **No manual generation** needed for new devices
- **Consistent format** across all devices

### QR Code Types:

- **Local Generation**: Uses PHP library (if GD extension available)
- **Online Fallback**: Uses reliable QR service (if local generation fails)
- **Always Works**: Guaranteed QR code generation

### QR Code Content:

Each QR code contains:

```json
{
  "device_id": "PC-2024-0001",
  "device_name": "Dell OptiPlex 7090",
  "lab_id": "LAB001",
  "timestamp": 1703123456
}
```

## Benefits of the Update

### Reliability:

- ❌ No more 404 errors from deprecated Google API
- ❌ No more broken QR code links
- ✅ Always working QR codes

### Performance:

- ✅ Faster QR code generation
- ✅ Better error handling
- ✅ Consistent user experience

### Future-Proof:

- ✅ Uses modern, maintained libraries
- ✅ Multiple fallback options
- ✅ Easy to maintain and update

## After the Update

### For Users:

- All existing devices will have working QR codes
- QR scanner will work reliably
- Device lookup will be faster and more accurate

### For Administrators:

- No more manual QR code generation needed
- New devices automatically get QR codes
- System is fully automated

## Troubleshooting

### If Update Fails:

1. Check database connection
2. Verify QR generator library is installed
3. Check file permissions
4. Review error messages in the web interface

### If Some Devices Aren't Updated:

1. Check the device status in the update interface
2. Verify device data is complete (device_id, device_name, lab_id)
3. Try updating individual devices if needed

## Files Modified/Created

### New Files:

- `update_existing_qr_codes.php` - Main update interface
- `QR_UPDATE_INSTRUCTIONS.md` - This instruction file

### Modified Files:

- `html pages/qr_generator.php` - Updated QR generation system
- `html pages/addPrinter.php` - Added automatic QR generation
- `html pages/index.php` - Added update button
- `html pages/inventory.php` - Added update button
- `composer.json` - Added QR code library dependency

## Support

If you encounter any issues with the QR code update:

1. Check the error messages in the web interface
2. Verify all dependencies are installed
3. Ensure database connectivity
4. Review the system logs for detailed error information

The update system is designed to be safe and will not damage existing data. It only updates the QR code field in the devices table.
