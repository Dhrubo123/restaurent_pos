# Clique Cafe HK526 Android POS

This Android 9 application opens the existing web POS and prints receipt HTML directly to the HK526 integrated USB thermal printer.

## Supported printer

- USB vendor ID: `10927`
- USB product ID: `8208`
- 80 mm / 576-dot ESC/POS raster output

The first launch asks for the POS server address. Use the same LAN address that opens the POS in the tablet browser, such as `http://192.168.68.10`.

The app menu contains **Test integrated printer**. Android may request USB access the first time. Allow it, then run the test again.

## Build

From `android-pos` run:

```powershell
.\gradlew.bat assembleDebug
```

The debug APK is written to:

`app/build/outputs/apk/debug/app-debug.apk`

Install it on a connected tablet with:

```powershell
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

The generic USB implementation must be verified on the physical HK526. If its integrated controller uses vendor-specific initialization instead of standard ESC/POS bulk output, the supplier SDK can later replace `UsbReceiptPrinter` without changing the React POS.
