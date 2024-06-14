# loyalty-program-customers

# 0.3.2

- Fixed a bug where customer points were added as string into a number field.
- Fixed a bug that let while loop `process_csv_file` function run more times then there were lines in the CSV file.

# 0.3.1

- Added check for SCV file
- Added some error logging to help with debugging
- Added functionality to delete customers who don't appear in the newest CSV provided.
- Added check for Customer ID, to skip if NaN is passed.
- Added processed customers information to settings page, still a bit buggy
- Split main funciton into smaller parts for easier debugging and maintainability
