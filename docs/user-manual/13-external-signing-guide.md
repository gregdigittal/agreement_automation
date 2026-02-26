# 13. External Signing Guide

This guide is for people outside of Digittal Group who have been asked to sign a document. You do not need a CCRS account, a password, or any special software. Everything happens in your web browser.

---

## You Have Received a Signing Request

You will receive an email from CCRS containing a signing invitation. The email includes:

- The **title** of the contract you are being asked to sign.
- The **name** of the person who sent the request.
- A **"Sign Document"** button that takes you directly to the signing page.

Click the button to open the signing page in your browser. No account or login is needed -- the link itself is your secure access to the document.

The link is valid for **7 days**. If it expires before you sign, ask the sender to resend the invitation.

---

## Viewing the Document

When you open the signing page, the contract is displayed in a built-in PDF viewer. Take the time to read through the entire document before signing.

- **Scroll through the document** to review all pages.
- If the sender has enabled **page-by-page viewing**, you must scroll through every page before the signing controls become available. A progress bar at the top of the page shows how many pages you have viewed (e.g., "Pages viewed: 3 of 12").
- If **page initials** are required, each page will display an **"Initial"** button. Click the button on each page to draw your initials in the small canvas that appears. The progress indicator shows how many pages you have initialed (e.g., "Pages initialed: 5 of 12").

You cannot skip ahead -- the system tracks which pages you have viewed and initialed. The signing controls remain disabled until all requirements are met.

---

## Signing the Document

Once you have reviewed the document (and initialed pages, if required), the signature area becomes active. You can choose from four methods to provide your signature.

### Draw

Use your mouse, trackpad, or finger (on a touchscreen device) to draw your signature on the canvas. If you make a mistake, click **Clear** and try again.

### Type

Type your full name into the text field. The system renders it as a signature-style image. This is the fastest method.

### Upload

Upload an existing image of your signature. The file must be a **PNG** or **JPEG** image. This is useful if you have a scanned copy of your handwritten signature saved on your device.

### Camera

Use your device's camera to photograph your handwritten signature on a piece of paper:

1. When prompted, click **Allow** to grant camera access.
2. A live camera preview appears on screen.
3. Write your signature on a plain piece of white paper.
4. Hold the paper up to the camera so your signature is clearly visible.
5. Click **Capture**.
6. The system processes the image automatically -- it removes the paper background and isolates your signature.
7. Review the result. If you are satisfied, click **Accept**. If not, click **Retake** to try again.

Camera signing requires a modern browser and an HTTPS connection. If the camera does not activate, check that you have granted permission in your browser settings.

---

## Using a Saved Signature

If you have signed documents through CCRS before and chose to save your signature, your stored signatures will appear in a **"Saved Signatures"** section on the signing page. Click any saved signature to select it immediately, without needing to draw, type, upload, or capture a new one.

If you do not have any saved signatures, this section will not appear.

---

## Submitting Your Signature

After choosing or creating your signature:

1. Review the signature preview to make sure it looks correct.
2. Click **Submit Signature**.
3. You will be asked whether you want to **save this signature for future use**. If you check this option, the next time you are asked to sign a document through CCRS, your signature will be available for one-click selection.
4. A confirmation message is displayed on screen.
5. You will receive a **confirmation email** once your signature has been recorded.

All parties involved in the signing process will be notified as the process progresses.

---

## Declining to Sign

If you are unable or unwilling to sign the document, you can decline:

1. Click **Decline** on the signing page.
2. Enter a **reason** for declining. This field is required.
3. Click **Confirm Decline**.

What happens after you decline:

- The sender is notified of your decision and can read the reason you provided.
- Your status is recorded as "declined" in the signing record.
- Declining is **final** for this signing session. You cannot change your mind and sign after declining. If circumstances change, the sender must create a new signing session.

---

## What Happens After You Sign

Once you submit your signature, the following steps occur automatically:

1. **Your signature is recorded** with a secure timestamp, your IP address, and the method you used.
2. **Other signers are notified** (if applicable):
   - In **sequential** signing, the next signer in the order receives their invitation email.
   - In **parallel** signing, other signers may have already received their links and can sign independently.
3. **When all signers have signed**, the system finalises the document:
   - All signatures are embedded into the final PDF at their designated positions.
   - An **audit certificate** is generated -- a separate document that records who signed, when, from which IP address, and the method used.
   - A **SHA-256 hash** of the completed document is computed for tamper detection.
4. **You receive a completion email** confirming that the signing process is finished and the document has been finalised.

---

## Troubleshooting

| Issue | Solution |
|---|---|
| **The link does not work** | The link may have expired. Signing links are valid for 7 days. Ask the sender to resend the invitation. |
| **Camera is not working** | Ensure you are using HTTPS (the address bar should show a padlock icon). Check that you have granted camera permission in your browser settings. Try a different browser if the issue persists. |
| **Cannot submit my signature** | Make sure you have viewed all required pages. If page initials are required, check that you have initialed every page. The progress indicators at the top of the page show your status. |
| **Page says "already signed"** | You have already submitted your signature for this document. No further action is needed. |
| **Page says "session expired"** | The signing session has expired. Contact the sender to request a new signing session. |
| **Browser compatibility** | Use a modern, up-to-date browser: Google Chrome, Mozilla Firefox, Apple Safari, or Microsoft Edge. Mobile browsers on iOS and Android are supported. Internet Explorer is not supported. |
| **Page loads slowly or PDF does not appear** | Check your internet connection. Try refreshing the page. If the problem continues, try a different browser or device. |
| **Accidentally closed the page** | You can re-open the signing link from your email as long as it has not expired and you have not already signed or declined. |

---

## Privacy and Security

Your security is important. Here is how the signing system protects you:

- **No account required** -- you do not need to create an account, set a password, or install any software.
- **Secure tokens** -- the link in your email contains a unique, cryptographically generated token. Only the hash of this token is stored in the system -- the link itself is the only place the full token exists.
- **Encrypted connection** -- all communication between your browser and the signing server uses HTTPS encryption.
- **Time-limited access** -- signing links expire after 7 days and sessions expire after 30 days.
- **Audit trail** -- every action you take on the signing page (viewing the document, signing, declining) is recorded with a timestamp and your IP address for legal accountability.
- **Document integrity** -- the document is hashed before and after signing. Any tampering with the document after signing would be detectable.

---

## Frequently Asked Questions

**Do I need to install anything?**
No. Everything runs in your web browser. No plugins, extensions, or desktop software are required.

**Can I sign on my phone or tablet?**
Yes. The signing page works on mobile devices. The Draw method works well with touchscreens.

**What if I need to sign multiple documents?**
Each document has its own signing link. You will receive a separate email for each document that requires your signature.

**Is my signature legally binding?**
Electronic signatures collected through CCRS are intended to be legally binding. The system records your identity, intent to sign, the timestamp, and the document content to support enforceability. Consult your legal advisor if you have specific questions about the legal validity of electronic signatures in your jurisdiction.

**Can the sender see my signature after I submit it?**
Yes. Your signature is embedded in the final signed PDF document, which is accessible to all parties involved in the contract.

**What if I have questions about the document content?**
Contact the person who sent you the signing request. Their name and organisation are included in the invitation email.

**Can I download a copy of the signed document?**
Once all parties have signed and the document is finalised, you will receive a completion email. Depending on the sender's configuration, this email may include a link to download the final signed document.
