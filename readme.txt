# ScanPro PDF & Image Optimizer

## Description

ScanPro PDF & Image Optimizer is a powerful WordPress plugin that provides image compression and PDF conversion tools to optimize your website's media files. With ScanPro, you can:

- Compress images (JPEG, PNG, GIF, WebP) to reduce file size while maintaining quality
- Convert PDF files to editable formats (Word, Excel)
- Convert PDF files to images (JPG, PNG)
- Convert various file formats (Word, Excel, PowerPoint, images) to PDF
- Automatically optimize new image uploads
- Bulk optimize existing images in your media library

## Installation

1. Download the plugin zip file from [scanpro.cc](https://scanpro.cc)
2. Log in to your WordPress admin panel
3. Navigate to Plugins > Add New
4. Click "Upload Plugin" and select the downloaded zip file
5. Click "Install Now" and then "Activate Plugin"
6. Go to the ScanPro settings page to enter your API key

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Valid ScanPro API key (get one at [scanpro.cc](https://scanpro.cc))

## Configuration

After activating the plugin, you need to configure it with your API key:

1. Navigate to ScanPro > Settings in your WordPress admin menu
2. Enter your ScanPro API key
3. Click "Validate Key" to ensure your key is working correctly
4. Configure other settings:
   - Auto-Compress Uploads: Enable/disable automatic compression of new uploads
   - Compression Quality: Select the level of compression (Low, Medium, High)
5. Click "Save Changes" to apply your settings

## Usage

### Image Compression

#### Automatic Compression
When enabled, all new image uploads will be automatically compressed according to your quality settings.

#### Manual Compression in Media Library
1. Go to Media > Library
2. Hover over an image and click "Edit"
3. In the attachment details, find the ScanPro section
4. Click "Optimize" to compress the image

#### Bulk Optimization
1. Navigate to ScanPro > Bulk Optimizer
2. View a list of all unoptimized images in your media library
3. Click "Optimize All Images" to process them in batch
4. Alternatively, optimize individual images by clicking the "Optimize" button next to each one

### PDF Tools

The plugin offers several PDF conversion tools:

#### Convert PDF to Word
1. Go to ScanPro > PDF Tools
2. In the "Convert PDF to Word" section, click "Choose PDF File"
3. Select a PDF file from your computer
4. Click "Convert to Word"
5. Once processing is complete, click "Download" to get your converted DOCX file

#### Convert PDF to Excel
1. Go to ScanPro > PDF Tools
2. In the "Convert PDF to Excel" section, click "Choose PDF File"
3. Select a PDF file from your computer
4. Click "Convert to Excel"
5. Once processing is complete, click "Download" to get your converted XLSX file

#### Convert PDF to JPG
1. Go to ScanPro > PDF Tools
2. In the "Convert PDF to JPG" section, click "Choose PDF File"
3. Select a PDF file from your computer
4. Click "Convert to JPG"
5. Once processing is complete, click "Download" to get your converted JPG file

#### Convert Files to PDF
1. Go to ScanPro > PDF Tools
2. In the "Convert to PDF" section, click "Choose File"
3. Select a file (DOCX, XLSX, PPTX, JPG, PNG) from your computer
4. Click "Convert to PDF"
5. Once processing is complete, click "Download" to get your converted PDF file

## File Size Limits

- Maximum file size for compression: 10MB
- Maximum file size for conversion: 15MB

## Supported File Formats

- Images: JPEG, PNG, GIF, WebP
- Documents: PDF, DOCX, XLSX, PPTX

## Conversion Paths

The plugin supports the following conversion paths:

- PDF → DOCX, XLSX, JPG, PNG, TXT
- DOCX → PDF
- XLSX → PDF
- PPTX → PDF
- JPG/JPEG → PDF
- PNG → PDF

## Troubleshooting

### API Key Issues
- Ensure your API key is correct and has been validated
- Check that your account has sufficient credits at [scanpro.cc](https://scanpro.cc)

### File Upload Problems
- Verify that your file is within the size limits
- Ensure the file format is supported
- Check your server's PHP configuration for upload limits (post_max_size, upload_max_filesize)

### Conversion Errors
- Make sure you're using a valid conversion path
- Some complex PDFs may not convert properly
- For best results, ensure original documents are well-formatted

## Privacy

The plugin sends your files to the ScanPro API for processing. Please review the [ScanPro Privacy Policy](https://scanpro.cc/privacy) for information on how your data is handled.

## License

GPL-2.0+

## Credits

ScanPro PDF & Image Optimizer is developed and maintained by [scanpro.cc](https://scanpro.cc).

## Support

For support, feature requests, or bug reports, please contact us through our website [scanpro.cc](https://scanpro.cc) or email support@scanpro.cc.