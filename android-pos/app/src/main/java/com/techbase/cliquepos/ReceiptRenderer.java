package com.techbase.cliquepos;

import android.graphics.Bitmap;
import android.graphics.Canvas;
import android.graphics.Color;
import android.view.View;
import android.view.ViewGroup;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;

final class ReceiptRenderer implements AutoCloseable {
    interface StatusListener {
        void onStatus(String message);
    }

    private static final int PAPER_WIDTH_DOTS = 576;
    private static final int MAX_RECEIPT_HEIGHT = 16000;
    private static final String THERMAL_STYLE = """
        <style>
          .thermal-print-root .kot-paper {
            padding: 16px 16px 12px !important;
            font-family: Roboto, "Noto Sans Bengali", Arial, sans-serif !important;
            font-size: 23px !important;
            line-height: 1.28 !important;
            font-weight: 500 !important;
            letter-spacing: normal !important;
            color: #000 !important;
          }
          .thermal-print-root .receipt-brand-logo { width: 52px; height: 52px; }
          .thermal-print-root .kot-paper-station { font-size: 29px; font-weight: 700; letter-spacing: normal; }
          .thermal-print-root .kot-paper-table,
          .thermal-print-root .kot-paper-number { font-size: 25px; }
          .thermal-print-root .kot-paper-label,
          .thermal-print-root .kot-paper-sub,
          .thermal-print-root .kot-paper-round,
          .thermal-print-root .kot-paper-meta,
          .thermal-print-root .kot-paper-footer,
          .thermal-print-root .receipt-dev-credit,
          .thermal-print-root .kot-paper-mods,
          .thermal-print-root .kot-paper-note { font-size: 19px !important; color: #000 !important; }
          .thermal-print-root .kot-paper-divider {
            height: 0;
            margin: 7px 0;
            overflow: hidden;
            border-top: 2px dashed #000;
          }
          .thermal-print-root .receipt-colhead,
          .thermal-print-root .receipt-line-row {
            display: grid !important;
            grid-template-columns: 60px minmax(0, 1fr) max-content;
            grid-column-gap: 10px;
            align-items: start;
            font-size: 23px;
          }
          .thermal-print-root .receipt-colhead { font-weight: 700; }
          .thermal-print-root .receipt-colhead > :last-child,
          .thermal-print-root .receipt-line-row > :last-child { text-align: right; white-space: nowrap; }
          .thermal-print-root .kot-paper-item-body { min-width: 0; word-wrap: break-word; }
          .thermal-print-root .receipt-totals { font-size: 22px; }
          .thermal-print-root .receipt-grand-total { font-size: 27px; font-weight: 700; }
          .thermal-print-root .receipt-totals > div > :last-child { white-space: nowrap; }
          .thermal-print-root .kot-paper-colhead,
          .thermal-print-root .kot-paper-line-row { grid-template-columns: 60px minmax(0, 1fr); }
          .thermal-print-root .kot-paper-item-name,
          .thermal-print-root .receipt-line-row .kot-paper-item-body > strong {
            min-width: 0;
            font-size: 30px !important;
            line-height: 1.2;
            overflow-wrap: break-word;
          }
          .thermal-print-root .kot-paper-qty { font-size: 23px; }
        </style>
        """;

    private final FrameLayout content;
    private final UsbReceiptPrinter printer;
    private final StatusListener statusListener;
    private WebView renderView;
    private boolean rendering;

    ReceiptRenderer(FrameLayout content, UsbReceiptPrinter printer, StatusListener statusListener) {
        this.content = content;
        this.printer = printer;
        this.statusListener = statusListener;
    }

    void print(String html, String title, String pageUrl) {
        if (rendering) {
            statusListener.onStatus("A receipt is already being prepared for printing.");
            return;
        }
        rendering = true;
        statusListener.onStatus("Preparing " + title + "…");

        renderView = new WebView(content.getContext());
        renderView.setBackgroundColor(Color.WHITE);
        renderView.setLayerType(View.LAYER_TYPE_SOFTWARE, null);
        renderView.setFocusable(false);
        renderView.setImportantForAccessibility(View.IMPORTANT_FOR_ACCESSIBILITY_NO_HIDE_DESCENDANTS);
        WebSettings settings = renderView.getSettings();
        settings.setJavaScriptEnabled(false);
        settings.setBlockNetworkImage(false);
        settings.setLoadsImagesAutomatically(true);

        FrameLayout.LayoutParams params = new FrameLayout.LayoutParams(
            PAPER_WIDTH_DOTS, FrameLayout.LayoutParams.MATCH_PARENT);
        content.addView(renderView, 0, params);
        renderView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                view.postVisualStateCallback(0, new WebView.VisualStateCallback() {
                    @Override
                    public void onComplete(long requestId) {
                        if (view == renderView) {
                            renderAndPrint(view);
                        }
                    }
                });
            }
        });
        WebView current = renderView;
        content.postDelayed(() -> {
            if (current == renderView) {
                statusListener.onStatus("Receipt preview timed out. Try printing again.");
                close();
            }
        }, 15000);
        // Match the POS page's origin so its crossorigin stylesheets can load.
        renderView.loadDataWithBaseURL(pageUrl, html.replace("</head>", THERMAL_STYLE + "</head>"),
            "text/html", "UTF-8", null);
    }

    private void renderAndPrint(WebView view) {
        try {
            int contentHeight = Math.max(1, (int) Math.ceil(view.getContentHeight() * view.getScale()));
            if (contentHeight > MAX_RECEIPT_HEIGHT) {
                throw new IllegalStateException("Receipt is too long to print safely.");
            }

            int widthSpec = View.MeasureSpec.makeMeasureSpec(PAPER_WIDTH_DOTS, View.MeasureSpec.EXACTLY);
            int heightSpec = View.MeasureSpec.makeMeasureSpec(contentHeight, View.MeasureSpec.EXACTLY);
            view.measure(widthSpec, heightSpec);
            view.layout(0, 0, PAPER_WIDTH_DOTS, contentHeight);

            Bitmap bitmap = Bitmap.createBitmap(PAPER_WIDTH_DOTS, contentHeight, Bitmap.Config.ARGB_8888);
            Canvas canvas = new Canvas(bitmap);
            canvas.drawColor(Color.WHITE);
            view.draw(canvas);
            printer.printBitmap(bitmap);
        } catch (RuntimeException exception) {
            statusListener.onStatus("Could not prepare receipt: " + exception.getMessage());
        } finally {
            removeRenderView();
            rendering = false;
        }
    }

    private void removeRenderView() {
        if (renderView == null) {
            return;
        }
        ViewGroup parent = (ViewGroup) renderView.getParent();
        if (parent != null) {
            parent.removeView(renderView);
        }
        renderView.stopLoading();
        renderView.destroy();
        renderView = null;
    }

    @Override
    public void close() {
        removeRenderView();
        rendering = false;
    }
}
