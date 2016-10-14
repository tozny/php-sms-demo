# Tozny SMS-based Phone Verification Demo

First, copy `.env.example` to `.env` and populate your Realm information.

Then run `run.sh` at the command line to start the local server.

Now visit http://localhost:8080 in a browser and go!

## Workflow

The first screen requires a phone number. Submitting said phone number will dispatch a Tozny OTP via SMS to your device.

The second screen requires the OTP you have received (and transparently tracks the OTP session - it will only be valid once and for the device you just attempted to validate).

The final screen (assuming the OTP validated) will display the raw signed_data/signature tuple returned from Tozny as well as the deserialized JSON that was bound to the OTP session.
