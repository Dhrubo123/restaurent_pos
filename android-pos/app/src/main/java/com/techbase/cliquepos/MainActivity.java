package com.techbase.cliquepos;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.view.Menu;
import android.view.MenuItem;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.Toast;

public final class MainActivity extends Activity {
    private static final String PREFS = "clique_pos";
    private static final String PREF_SERVER_URL = "server_url";
    private static final String DEFAULT_SERVER_URL = "https://demo.famousshoesbd.com/";
    private static final int MENU_RELOAD = 1;
    private static final int MENU_TEST_PRINTER = 2;
    private static final int MENU_SERVER = 3;
    private static final int PICK_FILE = 100;

    private WebView posWebView;
    private UsbReceiptPrinter printer;
    private ReceiptRenderer receiptRenderer;
    private ValueCallback<Uri[]> fileSelection;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setTitle("Clique Cafe POS");

        WebView.enableSlowWholeDocumentDraw();
        FrameLayout content = new FrameLayout(this);
        printer = new UsbReceiptPrinter(this, this::showStatus);
        receiptRenderer = new ReceiptRenderer(content, printer, this::showStatus);
        posWebView = new WebView(this);
        configureWebView(posWebView);
        content.addView(posWebView, new FrameLayout.LayoutParams(
            FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.MATCH_PARENT));
        setContentView(content);

        String savedUrl = preferences().getString(PREF_SERVER_URL, DEFAULT_SERVER_URL);
        if (savedUrl == null || savedUrl.trim().isEmpty()) {
            posWebView.loadUrl(DEFAULT_SERVER_URL);
        } else {
            posWebView.loadUrl(savedUrl);
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void configureWebView(WebView webView) {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE);
        settings.setUserAgentString(settings.getUserAgentString() + " CliquePosAndroid/1.0");

        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true);
        webView.setBackgroundColor(Color.WHITE);
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                installHostedPrintBridge(view);
            }
        });
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback,
                                             FileChooserParams params) {
                if (fileSelection != null) {
                    fileSelection.onReceiveValue(null);
                }
                fileSelection = callback;
                try {
                    startActivityForResult(params.createIntent(), PICK_FILE);
                } catch (ActivityNotFoundException exception) {
                    fileSelection = null;
                    callback.onReceiveValue(null);
                    showStatus("No file picker is available.");
                }
                return true;
            }
        });
        webView.addJavascriptInterface(new PrinterBridge(), "PosPrinter");
    }

    private void installHostedPrintBridge(WebView webView) {
        String script = "(function(){"
            + "if(window.__cliqueNativePrintInstalled){return;}"
            + "window.__cliqueNativePrintInstalled=true;"
            + "window.print=function(){"
            + "var source=document.querySelector('.thermal-print-root')||document.querySelector('.print-backdrop .kot-paper-wrap');"
            + "if(!source){return;}"
            + "var assets=Array.prototype.map.call(document.head.querySelectorAll('style,link[rel=\"stylesheet\"]'),function(node){return node.outerHTML;}).join('\\n');"
            + "var base=String(document.baseURI).replace(/&/g,'&amp;').replace(/\"/g,'&quot;');"
            + "var title=String(document.title||'Clique Cafe 80mm Slip');"
            + "var content=source.classList.contains('thermal-print-root')?source.innerHTML:source.innerHTML;"
            + "var css='<style>*{box-sizing:border-box!important}html,body{width:576px!important;min-width:576px!important;margin:0!important;padding:0!important;overflow:visible!important;background:#fff!important;color:#000!important}body>*{display:none!important}body>.thermal-print-root{display:block!important;width:576px!important;margin:0!important;padding:0!important;background:#fff!important}.thermal-print-root .kot-paper{display:block!important;width:576px!important;min-height:0!important;margin:0!important;padding:24px 20px!important;border:0!important;border-radius:0!important;box-shadow:none!important;background:#fff!important;color:#000!important;font-family:Courier New,Courier,monospace!important}.thermal-print-root .kot-paper-second-copy{margin-top:16px!important;padding-top:24px!important;border-top:2px dashed #000!important}</style>';"
            + "var html='<!doctype html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=576,initial-scale=1\"><base href=\"'+base+'\">'+assets+css+'</head><body><div class=\"thermal-print-root\">'+content+'</div></body></html>';"
            + "window.PosPrinter.printHtml(html,title);"
            + "};"
            + "})();";
        webView.evaluateJavascript(script, null);
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        menu.add(Menu.NONE, MENU_RELOAD, Menu.NONE, "Reload POS");
        menu.add(Menu.NONE, MENU_TEST_PRINTER, Menu.NONE, "Test integrated printer");
        menu.add(Menu.NONE, MENU_SERVER, Menu.NONE, "Change POS address");
        return true;
    }

    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        if (item.getItemId() == MENU_RELOAD) {
            posWebView.reload();
            return true;
        }
        if (item.getItemId() == MENU_TEST_PRINTER) {
            printer.printTestPage();
            return true;
        }
        if (item.getItemId() == MENU_SERVER) {
            showServerDialog(false);
            return true;
        }
        return super.onOptionsItemSelected(item);
    }

    private void showServerDialog(boolean required) {
        EditText input = new EditText(this);
        input.setSingleLine(true);
        input.setHint("http://192.168.68.10");
        input.setText(preferences().getString(PREF_SERVER_URL, ""));
        input.setSelectAllOnFocus(true);

        AlertDialog dialog = new AlertDialog.Builder(this)
            .setTitle("POS server address")
            .setMessage("Enter the address that opens the POS on this tablet.")
            .setView(input)
            .setCancelable(!required)
            .setPositiveButton("Save and open", null)
            .setNegativeButton(required ? null : "Cancel", null)
            .create();

        dialog.setOnShowListener(ignored -> dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(view -> {
            String value = normalizeUrl(input.getText().toString());
            if (value.isEmpty()) {
                input.setError("Enter the POS address");
                return;
            }
            preferences().edit().putString(PREF_SERVER_URL, value).apply();
            dialog.dismiss();
            posWebView.loadUrl(value);
        }));
        dialog.show();
    }

    private String normalizeUrl(String value) {
        String trimmed = value.trim();
        if (trimmed.isEmpty()) {
            return "";
        }
        if (!trimmed.startsWith("http://") && !trimmed.startsWith("https://")) {
            return "http://" + trimmed;
        }
        return trimmed;
    }

    private SharedPreferences preferences() {
        return getSharedPreferences(PREFS, MODE_PRIVATE);
    }

    private void showStatus(String message) {
        runOnUiThread(() -> Toast.makeText(this, message, Toast.LENGTH_LONG).show());
    }

    @Override
    public void onBackPressed() {
        if (posWebView.canGoBack()) {
            posWebView.goBack();
        } else {
            super.onBackPressed();
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == PICK_FILE && fileSelection != null) {
            ValueCallback<Uri[]> callback = fileSelection;
            fileSelection = null;
            Uri[] selection = WebChromeClient.FileChooserParams.parseResult(resultCode, data);
            if (selection != null) {
                for (Uri file : selection) {
                    if (!"content".equals(file.getScheme())) {
                        callback.onReceiveValue(null);
                        showStatus("Select a file from the Android file picker.");
                        return;
                    }
                }
            }
            callback.onReceiveValue(selection);
        }
    }

    @Override
    protected void onDestroy() {
        if (fileSelection != null) {
            fileSelection.onReceiveValue(null);
            fileSelection = null;
        }
        receiptRenderer.close();
        printer.close();
        posWebView.destroy();
        super.onDestroy();
    }

    public final class PrinterBridge {
        @JavascriptInterface
        public void printHtml(String html, String title) {
            runOnUiThread(() -> receiptRenderer.print(html, title, posWebView.getUrl()));
        }

        @JavascriptInterface
        public boolean isAvailable() {
            return printer.isPrinterConnected();
        }
    }
}
