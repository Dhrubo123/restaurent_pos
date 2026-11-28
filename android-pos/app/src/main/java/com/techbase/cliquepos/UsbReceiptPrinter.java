package com.techbase.cliquepos;

import android.annotation.SuppressLint;
import android.app.PendingIntent;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.graphics.Bitmap;
import android.graphics.Color;
import android.hardware.usb.UsbConstants;
import android.hardware.usb.UsbDevice;
import android.hardware.usb.UsbDeviceConnection;
import android.hardware.usb.UsbEndpoint;
import android.hardware.usb.UsbInterface;
import android.hardware.usb.UsbManager;
import android.os.Build;

import java.io.ByteArrayOutputStream;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

final class UsbReceiptPrinter implements AutoCloseable {
    interface StatusListener {
        void onStatus(String message);
    }

    private static final int VENDOR_ID = 10927;
    private static final int PRODUCT_ID = 8208;
    private static final int PAPER_WIDTH_DOTS = 576;
    private static final int RASTER_BAND_HEIGHT = 128;
    private static final String ACTION_USB_PERMISSION = "com.techbase.cliquepos.USB_PERMISSION";

    private final Context context;
    private final UsbManager usbManager;
    private final StatusListener statusListener;
    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private Bitmap pendingBitmap;

    private final BroadcastReceiver permissionReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context receiverContext, Intent intent) {
            if (!ACTION_USB_PERMISSION.equals(intent.getAction())) {
                return;
            }
            UsbDevice device = getUsbDevice(intent);
            if (device == null || !intent.getBooleanExtra(UsbManager.EXTRA_PERMISSION_GRANTED, false)) {
                recyclePendingBitmap();
                statusListener.onStatus("Printer permission was not granted.");
                return;
            }
            Bitmap bitmap = pendingBitmap;
            pendingBitmap = null;
            if (bitmap != null) {
                submitBitmap(bitmap);
            }
        }
    };

    @SuppressLint("UnspecifiedRegisterReceiverFlag")
    UsbReceiptPrinter(Context context, StatusListener statusListener) {
        this.context = context.getApplicationContext();
        this.statusListener = statusListener;
        this.usbManager = (UsbManager) context.getSystemService(Context.USB_SERVICE);
        IntentFilter filter = new IntentFilter(ACTION_USB_PERMISSION);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            this.context.registerReceiver(permissionReceiver, filter, Context.RECEIVER_NOT_EXPORTED);
        } else {
            this.context.registerReceiver(permissionReceiver, filter);
        }
    }

    boolean isPrinterConnected() {
        return findPrinter() != null;
    }

    void printTestPage() {
        UsbDevice device = findPrinter();
        if (device == null) {
            statusListener.onStatus("HK526 integrated printer 10927:8208 was not found.");
            return;
        }
        if (!usbManager.hasPermission(device)) {
            requestPermission(device);
            statusListener.onStatus("Allow USB printer access, then tap Test integrated printer again.");
            return;
        }
        executor.execute(() -> {
            ByteArrayOutputStream output = new ByteArrayOutputStream();
            append(output, new byte[]{0x1B, 0x40});
            append(output, new byte[]{0x1B, 0x61, 0x01});
            append(output, new byte[]{0x1B, 0x45, 0x01});
            append(output, "CLIQUE CAFE\n".getBytes(StandardCharsets.US_ASCII));
            append(output, new byte[]{0x1B, 0x45, 0x00});
            append(output, "HK526 INTEGRATED PRINTER TEST\n".getBytes(StandardCharsets.US_ASCII));
            append(output, "USB 10927:8208\n\n\n".getBytes(StandardCharsets.US_ASCII));
            append(output, new byte[]{0x1D, 0x56, 0x42, 0x00});
            send(device, output.toByteArray(), "Test receipt printed.");
        });
    }

    void printBitmap(Bitmap bitmap) {
        UsbDevice device = findPrinter();
        if (device == null) {
            bitmap.recycle();
            statusListener.onStatus("HK526 integrated printer 10927:8208 was not found.");
            return;
        }
        if (!usbManager.hasPermission(device)) {
            recyclePendingBitmap();
            pendingBitmap = bitmap;
            requestPermission(device);
            statusListener.onStatus("Allow access to the integrated printer.");
            return;
        }
        submitBitmap(bitmap);
    }

    private void submitBitmap(Bitmap bitmap) {
        executor.execute(() -> {
            UsbDevice device = findPrinter();
            if (device == null) {
                bitmap.recycle();
                statusListener.onStatus("Integrated printer disconnected before printing.");
                return;
            }
            try {
                int lastPrintedRow = lastPrintableRow(bitmap);
                if (lastPrintedRow < 0) {
                    statusListener.onStatus("Receipt preview was blank. No paper was printed.");
                    return;
                }
                int printHeight = Math.min(bitmap.getHeight(), lastPrintedRow + 1 + 16);
                printRaster(device, bitmap, printHeight);
                statusListener.onStatus("Receipt sent to the integrated printer.");
            } catch (RuntimeException exception) {
                statusListener.onStatus("Printing failed: " + exception.getMessage());
            } finally {
                bitmap.recycle();
            }
        });
    }

    private void printRaster(UsbDevice device, Bitmap bitmap, int printHeight) {
        PrinterConnection printerConnection = open(device);
        if (printerConnection == null) {
            throw new IllegalStateException("USB print endpoint is unavailable.");
        }
        try {
            write(printerConnection, new byte[]{0x1B, 0x40});
            int printableWidth = Math.min(PAPER_WIDTH_DOTS, bitmap.getWidth());
            int widthBytes = (printableWidth + 7) / 8;
            int[] pixels = new int[printableWidth * Math.min(RASTER_BAND_HEIGHT, printHeight)];
            for (int top = 0; top < printHeight; top += RASTER_BAND_HEIGHT) {
                int bandHeight = Math.min(RASTER_BAND_HEIGHT, printHeight - top);
                ByteArrayOutputStream band = new ByteArrayOutputStream(8 + widthBytes * bandHeight);
                band.write(0x1D);
                band.write(0x76);
                band.write(0x30);
                band.write(0x00);
                band.write(widthBytes & 0xFF);
                band.write((widthBytes >> 8) & 0xFF);
                band.write(bandHeight & 0xFF);
                band.write((bandHeight >> 8) & 0xFF);
                bitmap.getPixels(pixels, 0, printableWidth, 0, top, printableWidth, bandHeight);
                appendRasterData(band, pixels, bandHeight, printableWidth, widthBytes);
                write(printerConnection, band.toByteArray());
            }
            write(printerConnection, new byte[]{0x0A, 0x0A, 0x0A, 0x1D, 0x56, 0x42, 0x00});
        } finally {
            printerConnection.close();
        }
    }

    private void appendRasterData(
        ByteArrayOutputStream output,
        int[] pixels,
        int bandHeight,
        int width,
        int widthBytes
    ) {
        for (int row = 0; row < bandHeight; row++) {
            int rowOffset = row * width;
            for (int byteIndex = 0; byteIndex < widthBytes; byteIndex++) {
                int packed = 0;
                for (int bit = 0; bit < 8; bit++) {
                    int x = byteIndex * 8 + bit;
                    if (x < width && isBlack(pixels[rowOffset + x])) {
                        packed |= 1 << (7 - bit);
                    }
                }
                output.write(packed);
            }
        }
    }

    private int lastPrintableRow(Bitmap bitmap) {
        int width = Math.min(PAPER_WIDTH_DOTS, bitmap.getWidth());
        int[] row = new int[width];
        for (int y = bitmap.getHeight() - 1; y >= 0; y--) {
            bitmap.getPixels(row, 0, width, 0, y, width, 1);
            for (int pixel : row) {
                if (isBlack(pixel)) {
                    return y;
                }
            }
        }
        return -1;
    }

    private boolean isBlack(int pixel) {
        if (Color.alpha(pixel) < 64) {
            return false;
        }
        int luminance = (Color.red(pixel) * 299 + Color.green(pixel) * 587 + Color.blue(pixel) * 114) / 1000;
        return luminance < 175;
    }

    private void append(ByteArrayOutputStream output, byte[] bytes) {
        output.write(bytes, 0, bytes.length);
    }

    private void send(UsbDevice device, byte[] bytes, String successMessage) {
        PrinterConnection printerConnection = open(device);
        if (printerConnection == null) {
            statusListener.onStatus("USB print endpoint is unavailable.");
            return;
        }
        try {
            write(printerConnection, bytes);
            statusListener.onStatus(successMessage);
        } catch (RuntimeException exception) {
            statusListener.onStatus("Printing failed: " + exception.getMessage());
        } finally {
            printerConnection.close();
        }
    }

    private PrinterConnection open(UsbDevice device) {
        for (int interfaceIndex = 0; interfaceIndex < device.getInterfaceCount(); interfaceIndex++) {
            UsbInterface usbInterface = device.getInterface(interfaceIndex);
            UsbEndpoint outputEndpoint = null;
            for (int endpointIndex = 0; endpointIndex < usbInterface.getEndpointCount(); endpointIndex++) {
                UsbEndpoint endpoint = usbInterface.getEndpoint(endpointIndex);
                if (endpoint.getType() == UsbConstants.USB_ENDPOINT_XFER_BULK
                    && endpoint.getDirection() == UsbConstants.USB_DIR_OUT) {
                    outputEndpoint = endpoint;
                    break;
                }
            }
            if (outputEndpoint == null) {
                continue;
            }
            UsbDeviceConnection connection = usbManager.openDevice(device);
            if (connection != null && connection.claimInterface(usbInterface, true)) {
                return new PrinterConnection(connection, usbInterface, outputEndpoint);
            }
            if (connection != null) {
                connection.close();
            }
        }
        return null;
    }

    private void write(PrinterConnection printerConnection, byte[] bytes) {
        int offset = 0;
        while (offset < bytes.length) {
            int length = Math.min(16384, bytes.length - offset);
            int written = printerConnection.connection.bulkTransfer(
                printerConnection.endpoint,
                bytes,
                offset,
                length,
                5000
            );
            if (written <= 0) {
                throw new IllegalStateException("Printer stopped accepting data.");
            }
            offset += written;
        }
    }

    private UsbDevice findPrinter() {
        for (UsbDevice device : usbManager.getDeviceList().values()) {
            if (device.getVendorId() == VENDOR_ID && device.getProductId() == PRODUCT_ID) {
                return device;
            }
        }
        return null;
    }

    private void requestPermission(UsbDevice device) {
        Intent intent = new Intent(ACTION_USB_PERMISSION).setPackage(context.getPackageName());
        PendingIntent pendingIntent = PendingIntent.getBroadcast(
            context,
            0,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE
        );
        usbManager.requestPermission(device, pendingIntent);
    }

    @SuppressWarnings("deprecation")
    private UsbDevice getUsbDevice(Intent intent) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            return intent.getParcelableExtra(UsbManager.EXTRA_DEVICE, UsbDevice.class);
        }
        return intent.getParcelableExtra(UsbManager.EXTRA_DEVICE);
    }

    private void recyclePendingBitmap() {
        if (pendingBitmap != null) {
            pendingBitmap.recycle();
            pendingBitmap = null;
        }
    }

    @Override
    public void close() {
        recyclePendingBitmap();
        context.unregisterReceiver(permissionReceiver);
        executor.shutdownNow();
    }

    private static final class PrinterConnection implements AutoCloseable {
        private final UsbDeviceConnection connection;
        private final UsbInterface usbInterface;
        private final UsbEndpoint endpoint;

        private PrinterConnection(
            UsbDeviceConnection connection,
            UsbInterface usbInterface,
            UsbEndpoint endpoint
        ) {
            this.connection = connection;
            this.usbInterface = usbInterface;
            this.endpoint = endpoint;
        }

        @Override
        public void close() {
            connection.releaseInterface(usbInterface);
            connection.close();
        }
    }
}
