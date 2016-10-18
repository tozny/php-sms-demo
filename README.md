# Tozny SMS-based Phone Verification Demo

First, copy `.env.example` to `.env` and populate your Realm information.

Then run `run.sh` at the command line to start the local server.

Now visit http://localhost:8080 in a browser and go!

## Workflow

### Login

The login screen takes a standard username/password pair. If both are valid, the user is automatically redirected to /verify to verify the OTP they received on their device.

If the username or password are incorrect, authentication is rejected. There is no indication of _which_ was incorrect, merely that the credentials were invalid.

### Registration

Users are required to provide a username, a phone number and a password when registering. They must enter the same password _twice_ to register. Once submitted, the app will trigger a Tozny OTP challenge to the user's phone and redirect to a /confirm page where the user can verify their OTP.

Once the OTP is validated, the user is flagged as "verified" in the database and they are automatically logged in.

### Phone Verification

Once a user provides the OTP they were sent on their device, their account is considered "verified" and they are logged in. As a Tozny user is not required for this workflow, the app-local username is added to the OTP request initially and is used to populate the user session upon verification.

### Secured Data

Once logged in, the user can view their username and phone number, and can change thier password by entering their old password and a new password (twice).