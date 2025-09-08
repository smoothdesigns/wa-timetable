/** @format */

jQuery(document).ready(function ($) {
	// Wait for 2 seconds (adjust as needed)
	setTimeout(function () {
		var scriptTag = document.getElementById("__NEXT_DATA__");
		if (scriptTag) {
			try {
				var jsonData = JSON.parse(scriptTag.textContent);
				if (jsonData && jsonData.props && jsonData.props.pageProps && jsonData.props.pageProps.eventTimetable) {
					var eventTimetable = jsonData.props.pageProps.eventTimetable;
					var eventNameUrlSlug = jsonData.props.pageProps.page.event.nameUrlSlug; // Get nameUrlSlug from event
					var headers = wa_timetable_settings.headers || ["Time", "Sex", "Event", "Round"]; // Get headers from settings

					// Restructure data into days and then sessions
					var days = {};
					eventTimetable.forEach((event) => {
						var date = new Date(event.phaseDateAndTime).toLocaleDateString("en-US", {
							day: "numeric",
							month: "short",
						});
						if (!days[date]) {
							days[date] = {};
						}

						// Construct Phase Display
						var phaseDisplay = event.phaseName;
						if (event.unitTypeName && event.unitName) {
							phaseDisplay += " - " + event.unitTypeName + " " + event.unitName;
						}

						// Format time to 12-hour (remove leading zero)
						var time12Hour = new Date(event.phaseDateAndTime).toLocaleTimeString("en-US", {
							hour: "numeric", // Use "numeric" to remove leading zero
							minute: "2-digit",
							hour12: true,
						});

						var sessionName = event.phaseSessionName;
						if (!days[date][sessionName]) {
							days[date][sessionName] = [];
						}

						// Dynamically create the object keys based on the header settings
						var eventData = {};
						eventData[headers[0]] = time12Hour;
						eventData[headers[1]] = event.sexCode;
						eventData[headers[2]] = event.discipline.name;
						eventData[headers[3]] = phaseDisplay;

						eventData.sexNameUrlSlug = event.sexNameUrlSlug;
						eventData.nameUrlSlug = event.discipline.nameUrlSlug;
						eventData.phaseNameUrlSlug = event.phaseNameUrlSlug;
						eventData.isStartlistPublished = event.isStartlistPublished;
						eventData.isResultPublished = event.isResultPublished;
						eventData.isPhaseSummaryPublished = event.isPhaseSummaryPublished;

						days[date][sessionName].push(eventData);
					});

					// Generate HTML (same as in PHP)
					var output = '<div id="wa-timetable-tabs">';
					output += '<nav class="nav nav-underline flex-sm-wrap flex-md-row row-gap-1 column-gap-3" id="timetableTabs" role="tablist">';

					var today = new Date();
					var activeTabFound = false;

					Object.keys(days).forEach((dayKey, index) => {
						var tabId = "day-" + (index + 1);
						var dayNumber = new Date(dayKey).getDate();
						var dayMonth = new Date(dayKey).toLocaleDateString("en-US", {month: "short"});
						var isActive = false;

						if (new Date(dayKey + " " + today.getFullYear()).toLocaleDateString("en-US") === today.toLocaleDateString("en-US")) {
							isActive = true;
							activeTabFound = true;
						}

						var activeClass = isActive || (!activeTabFound && index === 0) ? "active" : "";
						var selected = isActive || (!activeTabFound && index === 0) ? "true" : "false";

						// Modified tab output to include day and month
						output += '<a class="nav-link text-sm-center d-flex flex-column flex-grow-1 lh-sm ' + activeClass + '" id="' + tabId + '-tab" data-toggle="tab" href="#' + tabId + '" role="tab" aria-controls="' + tabId + '" aria-selected="' + selected + '">';
						output += "<span>DAY " + (index + 1) + "</span>"; // Day banner - "Day 1", "Day 2" etc.
						output += "<span class='small'>";
						output += dayNumber + " " + dayMonth;
						output += "</span>";
						output += "</a>";
					});

					output += "</nav>";
					output += '<div class="tab-content" id="timetableTabsContent">';

					Object.keys(days).forEach((dayKey, index) => {
						var tabId = "day-" + (index + 1);
						var activeClass = !activeTabFound && index === 0 ? "show active" : "";
						if (dayKey.match(/(\\d{1,2} [A-Za-z]+)/)) {
							var matchDate = dayKey.match(/(\\d{1,2} [A-Za-z]+)/)[1];
							if (new Date(matchDate + " " + today.getFullYear()).toLocaleDateString("en-US") === today.toLocaleDateString("en-US")) {
								activeClass = "show active";
							}
						} else if (activeTabFound) {
							activeClass = "";
						}

						output += '<div class="tab-pane fade ' + activeClass + '" id="' + tabId + '" role="tabpanel" aria-labelledby="' + tabId + '-tab">';

						// Day Banner
						output += '<div class="day-banner bg-warning-subtle text-warning-emphasis w-75 border-bottom border-2 border-black p-2 m-0">';
						output += "<p class='fs-6 lh-1 mb-0'>DAY " + (index + 1) + " - " + dayKey + "</p>";
						output += "</div>";

						var daySessions = days[dayKey];
						if (daySessions) {
							// Accordion wrapper starts here - ONE accordion per day
							var accordionId = "accordion-" + dayKey.replace(/[^a-z0-9]/gi, "-");
							output += '<div class="accordion accordion-flush" id="' + accordionId + '">';

							var sessionCounter = 0;
							for (const sessionName in daySessions) {
								if (daySessions.hasOwnProperty(sessionName)) {
									const sessionEvents = daySessions[sessionName];
									const sessionAccordionId = "session-collapse-" + (sessionName + "-" + index + "-" + sessionCounter).replace(/[^a-z0-9]/gi, "-");
									const sessionHeadingId = "heading-" + (sessionName + "-" + index + "-" + sessionCounter).replace(/[^a-z0-9]/gi, "-");

									output += '<div class="accordion-item border-0">';
									output += '<h2 class="accordion-header pb-0" id="' + sessionHeadingId + '">';
									output += '<button class="accordion-button rounded-0 text-uppercase fs-4 ps-2" type="button" data-bs-toggle="collapse" data-bs-target="#' + sessionAccordionId + '" aria-expanded="true" aria-controls="' + sessionAccordionId + '">';
									output += sessionName; // Session name as title
									output += "</button>";
									output += "</h2>";

									output += '<div id="' + sessionAccordionId + '" class="accordion-collapse collapse show" aria-labelledby="' + sessionHeadingId + '">';
									output += '<div class="accordion-body p-0">';

									// Table for all events in the session
									output += '<div class="table-responsive shadow">';
									output += '<table class="table table-striped table-hover table-sm mb-0">';
									output += "<thead><tr>";

									headers.forEach((header) => {
										output += "<th scope='col'>" + header + "</th>";
									});

									output += "<th></th><th></th><th></th>"; // Add 3 empty headers
									output += "</tr></thead>";
									output += "<tbody>";
									sessionEvents.forEach((event) => {
										output += "<tr scope='row'>";
										headers.forEach((header) => {
											output += "<td>" + event[header] + "</td>";
										});

										// Add links if available (adjusted base URL)
										var baseUrl = "https://worldathletics.org/en/competitions/world-athletics-championships/" + eventNameUrlSlug + "/results/";

										var startlist_link = event.isStartlistPublished ? '<a href="' + baseUrl + event.sexNameUrlSlug + "/" + event.nameUrlSlug + "/" + event.phaseNameUrlSlug + '/startlist">Startlist</a>' : '<a href="' + baseUrl + event.sexNameUrlSlug + "/" + event.nameUrlSlug + "/" + event.phaseNameUrlSlug + '/startlist" target="_blank"></a>';
										var result_link = event.isResultPublished ? '<a href="' + baseUrl + event.sexNameUrlSlug + "/" + event.nameUrlSlug + "/" + event.phaseNameUrlSlug + '/result">Result</a>' : '<a href="' + baseUrl + event.sexNameUrlSlug + "/" + event.nameUrlSlug + "/" + event.phaseNameUrlSlug + '/result" target="_blank"></a>';
										var summary_link = event.isPhaseSummaryPublished ? '<a href="' + baseUrl + event.sexNameUrlSlug + "/" + event.nameUrlSlug + "/" + event.phaseNameUrlSlug + '/summary">Summary</a>' : '<a href="' + baseUrl + event.sexNameUrlSlug + "/" + event.nameUrlSlug + "/" + event.phaseNameUrlSlug + '/summary" target="_blank"></a>';

										output += "<td>" + startlist_link + "</td>";
										output += "<td>" + result_link + "</td>";
										output += "<td>" + summary_link + "</td>";
										output += "</tr>";
									});
									output += "</tbody>";
									output += "</table>";

									output += "</div>"; // table-responsive
									output += "</div>"; // accordion-body
									output += "</div>"; // collapse
									output += "</div>"; // accordion-item
									sessionCounter++;
								}
							}
							output += "</div>"; // outer accordion
						} else {
							output += "<p>No events scheduled for this day.</p>";
						}
						output += "</div>";
					});

					output += "</div>";
					output += "</div>";

					// Store the generated HTML in the hidden div
					$("#wa-timetable-data").html(output);
					$("#wa-timetable-container").html(output);

					// Initialize accordion behavior (open one at a time)
					$(".accordion").each(function () {
						$(this).find(".collapse").first().addClass("show");
					});

					// Accordion item click
					$(".accordion-button").on("click", function () {
						var target = $(this).data("target");
						$(target).toggleClass("show");
						$(this).attr("aria-expanded", $(target).hasClass("show"));
					});
				} else {
					$("#wa-timetable-container").html('<div class="alert alert-warning">Timetable data not found after delay. The structure might have changed.</div>');
				}
			} catch (e) {
				$("#wa-timetable-container").html('<div class="alert alert-danger">Error parsing timetable data: ' + e.message + "</div>");
			}
		} else {
			$("#wa-timetable-container").html('<div class="alert alert-warning">__NEXT_DATA__ script tag not found after delay.</div>');
		}
	}, 1500); // Wait 1.5 seconds
});
