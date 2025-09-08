/** @format */

(function ($) {
	"use strict";

	$(document).ready(function () {
		const getStoredTheme = () => localStorage.getItem("theme");
		const setStoredTheme = (theme) => localStorage.setItem("theme", theme);

		const getPreferredTheme = () => {
			const storedTheme = getStoredTheme();
			if (storedTheme) {
				return storedTheme;
			}

			return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
		};

		const setTheme = (theme) => {
			if (theme === "auto") {
				document.documentElement.setAttribute("data-bs-theme", window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
			} else {
				document.documentElement.setAttribute("data-bs-theme", theme);
			}
		};

		setTheme(getPreferredTheme());

		const showActiveTheme = (theme, focus = false) => {
			const themeSwitcher = $("#bd-theme").get(0); // Use jQuery to select by ID

			if (!themeSwitcher) {
				return;
			}

			const themeSwitcherText = $("#bd-theme-text").get(0); // Use jQuery to select by ID
			const activeThemeIcon = $(".theme-icon-active use").get(0); // Use jQuery to select by class
			const btnToActive = $(`[data-bs-theme-value="${theme}"]`).get(0); // Use jQuery selector
			const svgOfActiveBtn = btnToActive ? $(btnToActive).find("svg use").attr("href") : null; // Use jQuery to find

			$("[data-bs-theme-value]").each(function () {
				// Use jQuery's each
				$(this).removeClass("active");
				$(this).attr("aria-pressed", "false");
			});

			if (btnToActive) {
				$(btnToActive).addClass("active");
				$(btnToActive).attr("aria-pressed", "true");
				if (activeThemeIcon && svgOfActiveBtn) {
					activeThemeIcon.setAttribute("href", svgOfActiveBtn);
				}
				const themeSwitcherLabel = `${themeSwitcherText.textContent} (${$(btnToActive).data("bs-theme-value")})`; // Use jQuery's data
				themeSwitcher.setAttribute("aria-label", themeSwitcherLabel);

				if (focus) {
					themeSwitcher.focus();
				}
			}
		};

		window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
			const storedTheme = getStoredTheme();
			if (storedTheme !== "light" && storedTheme !== "dark") {
				setTheme(getPreferredTheme());
			}
		});

		showActiveTheme(getPreferredTheme());

		$("[data-bs-theme-value]").each(function () {
			// Use jQuery's each
			$(this).on("click", function () {
				// Use jQuery's on for event binding
				const theme = $(this).data("bs-theme-value"); // Use jQuery's data
				setStoredTheme(theme);
				setTheme(theme);
				showActiveTheme(theme, true);
			});
		});
	});
})(jQuery);
