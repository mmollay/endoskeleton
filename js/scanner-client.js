(function (global) {
  "use strict";

  var BASE_URL = "api/scan-proxy.php";
  var _pollingInterval = null;

  var ScannerClient = {
    /**
     * Strip protocol, www, paths, trailing slashes from a domain input.
     * e.g. "https://www.blindenhund.at/kontakt" → "blindenhund.at"
     */
    cleanDomain: function (input) {
      if (!input) return "";
      var domain = String(input).trim();
      // Remove protocol
      domain = domain.replace(/^https?:\/\//i, "");
      // Remove www.
      domain = domain.replace(/^www\./i, "");
      // Remove path, query, hash — keep only host
      domain = domain.split("/")[0];
      domain = domain.split("?")[0];
      domain = domain.split("#")[0];
      // Remove trailing dots or slashes
      domain = domain.replace(/[\/\.]+$/, "");
      return domain.toLowerCase();
    },

    /**
     * Check scanner cache for an existing scan result.
     * GET /api/v1/scans/{domain}
     * Returns parsed JSON or null on error/not-found.
     */
    checkCache: function (domain) {
      var url = BASE_URL + "?path=scans/" + encodeURIComponent(domain);
      return fetch(url, {
        method: "GET",
        headers: { Accept: "application/json" },
      })
        .then(function (response) {
          if (!response.ok) return null;
          return response.json().catch(function () {
            return null;
          });
        })
        .catch(function () {
          return null;
        });
    },

    /**
     * Start a quick scan (1 page).
     * POST /api/v1/scan
     * Throws on HTTP error.
     */
    quickScan: function (domain) {
      var url = BASE_URL + "?path=scan";
      var payload = {
        domain: domain,
        max_pages: 1,
        download_images: true,
        screenshots: true,
        analyze: true,
        ai_provider: "gemini",
      };
      return fetch(url, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      }).then(function (response) {
        if (!response.ok) {
          return response.text().then(function (text) {
            throw new Error(
              "quickScan failed (" + response.status + "): " + text,
            );
          });
        }
        return response.json();
      });
    },

    /**
     * Start a full scan (up to 30 pages).
     * POST /api/v1/scan
     * Throws on HTTP error.
     */
    fullScan: function (domain) {
      var url = BASE_URL + "?path=scan";
      var payload = {
        domain: domain,
        max_pages: 30,
        download_images: true,
        screenshots: true,
        analyze: true,
        ai_provider: "gemini",
      };
      return fetch(url, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      }).then(function (response) {
        if (!response.ok) {
          return response.text().then(function (text) {
            throw new Error(
              "fullScan failed (" + response.status + "): " + text,
            );
          });
        }
        return response.json();
      });
    },

    /**
     * Refine scan data with KI (Gemini).
     * POST /api/v1/refine
     * Returns refined text content for hero, about, services sections.
     */
    refine: function (domain, scanData, sections) {
      var url = BASE_URL + "?path=refine";
      var payload = {
        domain: domain,
        scan_data: scanData,
        sections: sections || ["all"],
      };
      return fetch(url, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      }).then(function (response) {
        if (!response.ok) {
          return response.json().then(function (err) {
            throw new Error(
              err.error || err.message || "Refinement fehlgeschlagen",
            );
          });
        }
        return response.json();
      });
    },

    /**
     * Poll the cache every 5 seconds.
     * Calls onUpdate(result) on each successful response.
     * Stops automatically when result.status === 'complete'.
     */
    startPolling: function (domain, onUpdate) {
      // Clear any existing interval first
      ScannerClient.stopPolling();

      _pollingInterval = setInterval(function () {
        ScannerClient.checkCache(domain).then(function (result) {
          if (!result) return;
          if (typeof onUpdate === "function") {
            onUpdate(result);
          }
          if (result.status === "complete") {
            ScannerClient.stopPolling();
          }
        });
      }, 5000);
    },

    /**
     * Stop any active polling interval.
     */
    stopPolling: function () {
      if (_pollingInterval !== null) {
        clearInterval(_pollingInterval);
        _pollingInterval = null;
      }
    },
  };

  global.ScannerClient = ScannerClient;
})(typeof window !== "undefined" ? window : this);
