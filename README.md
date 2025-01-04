# Change WordPress Upload's Directory

Allows administrators to change the WordPress upload directory without editing wp-config.php, migrating old media files (including thumbnails) to the new folder automatically.

## Features

- Custom Upload Path: Set any directory (relative or absolute) from the WP Admin settings.
- Automatic Folder Creation: Creates the new upload directory if it doesn’t exist.
- Full Media Migration: Moves both the primary image file and all generated thumbnails to the new location.
- Updates Database References: Ensures _wp_attached_file and _wp_attachment_metadata are updated so Media Library previews continue to work.
- Security-Focused: Only administrators can change settings; file paths are sanitized to prevent directory traversal or invalid characters.
## Usage
1. Go to Settings → Change Upload Directory.
2. Enter your desired path in the Custom Upload Directory Path field.
3. Relative paths (e.g., wp-content/custom-uploads) will be appended to your WordPress root path.
4. Absolute paths (e.g., /var/www/example.com/uploads) will be used directly.
5. Save Changes.
### Important Note
**Migrating Existing Files:** When you save a new path, the plugin attempts to move all old media (main files + thumbnails) from the old folder to the new folder in a single pass.
Back Up First: If you have a large media library, it’s recommended to back up both your database and your wp-content/uploads folder before making changes. On some hosts, this process can time out if there are thousands of images.

### Requirements
- WordPress: 4.7 or higher (though recommended to use the latest version).
- PHP: 7.0 or higher is recommended.
- Permissions: You must have permissions to create folders on your hosting environment (WordPress typically manages this automatically).


## More Information
For more information, visit [sushyant.com](https://sushyant.com).

## License
This plugin is licensed under the GPL V3. See the LICENSE file for more information.
