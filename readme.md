# Gmail CSV Downloader Plugin Setup

This guide provides the steps required to set up Google API credentials for the Gmail CSV Downloader WordPress plugin. These credentials allow the plugin to securely access your Gmail account and process CSV attachments in your inbox.

---

## Prerequisites

- A Google account
- Access to the [Google Cloud Console](https://console.cloud.google.com/)
- Admin access to the WordPress site where this plugin will be installed

---

## Step 1: Set Up a Google Cloud Project

1. **Open the Google Cloud Console**:
   - Navigate to the [Google Cloud Console](https://console.cloud.google.com/).
2. **Create a New Project**:

   - Click on the project dropdown in the top navigation bar.
   - Click **New Project**.
   - Enter a name for your project, select your organization if applicable, and click **Create**.

3. **Select the Project**:
   - After creation, select your new project from the project dropdown menu.

---

## Step 2: Enable the Gmail API

1. **Navigate to the API Library**:

   - In the left sidebar, go to **APIs & Services > Library**.

2. **Search for Gmail API**:

   - Type "Gmail API" in the search bar.

3. **Enable the Gmail API**:
   - Click on **Gmail API** from the search results.
   - Click the **Enable** button to activate it for your project.

---

## Step 3: Configure OAuth Consent Screen

1. **Navigate to OAuth Consent Screen**:

   - Go to **APIs & Services > OAuth consent screen** in the left sidebar.

2. **Configure the Consent Screen**:

   - **User Type**: Select **External** if you want the app to be available publicly, or **Internal** if you only need access within your G Suite organization.
   - **App Information**: Fill in the **App name**, **User support email**, and **Developer contact information**.

3. **Add Scopes**:

   - Under **Scopes**, click **Add or Remove Scopes**.
   - Add the following scopes:
     - `https://www.googleapis.com/auth/gmail.readonly` – allows the plugin to read Gmail messages.

4. **Save and Continue**:
   - Complete the consent screen setup.
   - You can leave the **Test Users** section blank if in production mode; otherwise, add specific users for testing.

---

## Step 4: Create OAuth 2.0 Credentials

1. **Navigate to Credentials**:

   - Go to **APIs & Services > Credentials** in the left sidebar.

2. **Create Credentials**:

   - Click **Create Credentials** and select **OAuth client ID**.

3. **Configure OAuth Client ID**:

   - **Application Type**: Select **Web Application**.
   - **Name**: Give it a name, e.g., "Gmail CSV Downloader".
   - **Authorized Redirect URIs**:
     - Add the URI that Google will redirect to after authentication.
     - For WordPress, this is typically `https://yourdomain.com/wp-admin/admin-post.php?action=gmail_authenticate` (replace `yourdomain.com` with your actual domain).

4. **Create and Download Credentials**:
   - Click **Create** to generate your OAuth client.
   - Click **Download JSON** to save the `credentials.json` file, which contains your client ID and secret.

---

## Step 5: Add `credentials.json` to the Plugin Directory

1. **Move `credentials.json` to the Plugin Directory**:

   - Place the downloaded file in your plugin directory (e.g., `wp-content/plugins/your-plugin/credentials.json`).

2. **Update the Plugin Code to Use `credentials.json`**:
   - In your PHP code, make sure to load `credentials.json`:
     ```php
     $client->setAuthConfig(__DIR__ . '/credentials.json');
     ```

---

## Step 6: Authenticate the Plugin with Google

1. **Go to the Plugin Settings Page**:

   - Open your WordPress site, navigate to the plugin settings page.

2. **Authenticate with Google**:

   - Click **Authenticate with Google**.
   - Log in to your Google account when prompted and allow access.

3. **Confirm Authentication**:
   - After successfully authenticating, Google will redirect back to your WordPress site, and your plugin should be authorized to access the Gmail API.

---

## Troubleshooting

- **Error: "Request had insufficient authentication scopes"**: Ensure that `https://www.googleapis.com/auth/gmail.modify` scope is included and that you have reauthorized the app.
- **No Redirect After Authentication**: Double-check that the **Authorized Redirect URI** in Google Cloud Console matches the plugin’s callback URI exactly.

With these steps completed, your WordPress plugin should be fully authenticated with Google and able to access the Gmail API as configured.
