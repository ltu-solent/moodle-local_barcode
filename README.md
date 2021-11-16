# Local Barcode Scanning Plugin

### Introduction

This plugin allows a user to scan barcodes that have been linked to an assignment submission. Once the student has printed off their cover sheet from the physical assignment, they are able to hand their submission into a member of staff for submission. The barcode scanning interface allows the member of staff to either scan the submission barcode or manually enter the barcode and upload the submission which is then marked as submitted.

In addition to submitting a physical submission, staff can also revert an already submitted assignment back to draft status.

In the event that a student has queued to submit their assignment on time but the queue has prevented them from submitting on time, the staff member that is scanning the submissions can choose to "Allow late submission", acknowledging that the submission was to be scanned on time, preventing a late submission.

### Installation

The plugin requires an authorisation token to authenticate the web service request. While Moodle allows each user to set their own token for a web service, administration permitting, an alternative is to assign an authorisation token to a single user for the service and have the one user and their token used for authentication.

If creating a new user for the sole purpose of authenticating the Barcode Scanning web service, create the new user before proceeding.

To set an authorisation token for the plugin, go to Site Administration -> Plugins -> Web services -> External services. There's a list of services, we're looking for Barcode Scanning and click the Authorised users link. Use this section to add a user to the web service.

Now navigate to Plugins -> Web services -> Manage Tokens and click add. Search for the user that was just added and select the Barcode Scanning service, leaving the Valid until option deselected. Now save the changes and the web service is ready to be used.

### Navigation

Users can access the barcode scanning interface if they have the plugins capability 'assignsubmission/physical:scan'. There is a button added to the grading workflow which allows the user to navigate to the barcode scanning page. There is also a direct link to a scanning page which allows admins to set a direct link in Moodle for users to navigate to without accessing the grading workflow. The url is local/barcode/assign/submisison.php
