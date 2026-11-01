// main.js

document.addEventListener("DOMContentLoaded", function () {
    const navigationToggle = document.querySelector(".site-nav-toggle");
    const navigation = document.getElementById("primary-navigation");

    if (navigationToggle && navigation) {
        navigationToggle.addEventListener("click", () => {
            const isExpanded = navigationToggle.getAttribute("aria-expanded") === "true";
            navigationToggle.setAttribute("aria-expanded", String(!isExpanded));
            navigationToggle.setAttribute("aria-label", isExpanded ? "Open navigation" : "Close navigation");
            navigation.classList.toggle("is-open", !isExpanded);
        });
    }

    /* ===============================
       Dashboard Card Click Effect
    =============================== */
    const cards = document.querySelectorAll(".dashboard-card");

    cards.forEach(card => {
        card.addEventListener("click", function () {
            card.style.border = "2px solid #0a66c2";
            setTimeout(() => {
                card.style.border = "none";
            }, 300);
        });
    });

    /* ===============================
       Live Job Search Filter
    =============================== */
    const searchInput = document.getElementById("jobSearch");

    if (searchInput) {
        searchInput.addEventListener("keyup", function () {
            let filter = this.value.toLowerCase();
            let jobs = document.querySelectorAll(".job-card");

            jobs.forEach(job => {
                let text = job.innerText.toLowerCase();
                job.style.display = text.includes(filter) ? "block" : "none";
            });
        });
    }

});


/* ===============================
   Popup Notification
=============================== */
function showPopup(message) {
    let popup = document.createElement("div");
    popup.className = "popup";
    popup.innerText = message;

    document.body.appendChild(popup);
    popup.style.display = "block";

    setTimeout(() => {
        popup.remove();
    }, 3000);
}


/* ===============================
   Dark Mode Toggle
=============================== */
function toggleDarkMode() {
    document.body.classList.toggle("dark");
}
document.addEventListener("DOMContentLoaded", function () {

    const applyBtn = document.getElementById("applyBtn");
    if (document.body.classList.contains("dark")) {
        localStorage.setItem("darkMode", "enabled");
     }
      if (applyBtn) {
        applyBtn.addEventListener("click", function () {

            let jobId = this.dataset.job;

            fetch("/victory/jobseeker/apply-job.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "job_id=" + jobId
            })
            .then(response => response.json())
            .then(data => {

                if (data.status === "success") {
                    showPopup("Application Submitted Successfully!");
                    applyBtn.innerText = "Applied";
                    applyBtn.disabled = true;
                }

                else if (data.status === "already") {
                    showPopup("You already applied for this job.");
                }



                else {
                    showPopup("Something went wrong.");
                    localStorage.setItem("darkMode", "disabled");
                }

            });


        });
      // load saved preference
 if (localStorage.getItem("darkMode") === "enabled") {
    document.body.classList.add("dark");
}
    }

});
// Scroll reveal animation
const revealElements = document.querySelectorAll(".reveal");

function revealOnScroll() {
    revealElements.forEach(el => {
        const windowHeight = window.innerHeight;
        const elementTop = el.getBoundingClientRect().top;

        if (elementTop < windowHeight - 100) {
            el.classList.add("active");
        }
    });
}

window.addEventListener("scroll", revealOnScroll);
// Animated counters
const counters = document.querySelectorAll(".counter");

counters.forEach(counter => {
    const updateCounter = () => {
        const target = +counter.getAttribute("data-target");
        const count = +counter.innerText;

        const increment = target / 100;

        if (count < target) {
            counter.innerText = Math.ceil(count + increment);
            setTimeout(updateCounter, 20);
        } else {
            counter.innerText = target;
        }
    };

    updateCounter();
});
// Auto slider
const slider = document.querySelector(".job-grid");

if (slider) {
    let scrollAmount = 0;

    setInterval(() => {
        scrollAmount += 260;
        if (scrollAmount >= slider.scrollWidth - slider.clientWidth) {
            scrollAmount = 0;
        }
        slider.scrollTo({
            left: scrollAmount,
            behavior: "smooth"
        });
    }, 3000);
}
function toggleSettings() {
    const panel = document.getElementById("settingsPanel");
    panel.style.display =
        panel.style.display === "block" ? "none" : "block";
}// ===============================
// RUN AFTER PAGE LOAD
// ===============================
document.addEventListener("DOMContentLoaded", () => {

    /* ===============================
       Dashboard Card Click Effect
    =============================== */
    document.querySelectorAll(".dashboard-card").forEach(card => {
        card.addEventListener("click", () => {
            card.style.border = "2px solid #0a66c2";
            setTimeout(() => card.style.border = "none", 300);
        });
    });

    /* ===============================
       Live Job Search Filter
    =============================== */
    const searchInput = document.getElementById("jobSearch");

    if (searchInput) {
        searchInput.addEventListener("keyup", function () {
            const filter = this.value.toLowerCase();

            document.querySelectorAll(".job-card-modern").forEach(job => {
                job.style.display =
                    job.innerText.toLowerCase().includes(filter)
                    ? "block" : "none";
            });
        });
    }

    /* ===============================
       Apply Job AJAX
    =============================== */
    const applyBtn = document.getElementById("applyBtn");

    if (applyBtn) {
        applyBtn.addEventListener("click", function () {

            const jobId = this.dataset.job;

            fetch("/victory/jobseeker/apply-job.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "job_id=" + jobId
            })
            .then(res => res.json())
            .then(data => {

                if (data.status === "success") {
                    showPopup("Application Submitted!");
                    applyBtn.innerText = "Applied";
                    applyBtn.disabled = true;
                }
                else if (data.status === "already") {
                    showPopup("Already applied.");
                }
                else {
                    showPopup("Something went wrong.");
                }

            });
        });
    }

    /* ===============================
       Scroll Reveal Animation
    =============================== */
    const revealElements = document.querySelectorAll(".reveal");

    function revealOnScroll() {
        revealElements.forEach(el => {
            const windowHeight = window.innerHeight;
            const elementTop = el.getBoundingClientRect().top;

            if (elementTop < windowHeight - 100) {
                el.classList.add("active");
            }
        });
    }

    window.addEventListener("scroll", revealOnScroll);
    revealOnScroll();

    /* ===============================
       Animated Counters
    =============================== */
    document.querySelectorAll(".counter").forEach(counter => {

        const updateCounter = () => {
            const target = +counter.getAttribute("data-target");
            const count = +counter.innerText;
            const increment = target / 100;

            if (count < target) {
                counter.innerText = Math.ceil(count + increment);
                setTimeout(updateCounter, 20);
            } else {
                counter.innerText = target;
            }
        };

        updateCounter();
    });

    /* ===============================
       Auto Slider for Jobs
    =============================== */
    const slider = document.querySelector(".job-grid");

    if (slider) {
        let scrollAmount = 0;

        setInterval(() => {
            scrollAmount += 260;

            if (scrollAmount >= slider.scrollWidth - slider.clientWidth) {
                scrollAmount = 0;
            }

            slider.scrollTo({
                left: scrollAmount,
                behavior: "smooth"
            });

        }, 3000);
    }

    /* ===============================
       Load Dark Mode Preference
    =============================== */
    if (localStorage.getItem("darkMode") === "enabled") {
        document.body.classList.add("dark");
    }

});


/* ===============================
   Popup Notification
=============================== */
function showPopup(message) {
    const popup = document.createElement("div");
    popup.className = "popup";
    popup.innerText = message;

    document.body.appendChild(popup);
    popup.style.display = "block";

    setTimeout(() => popup.remove(), 3000);
}


/* ===============================
   Dark Mode Toggle
=============================== */
function toggleDarkMode() {
    document.body.classList.toggle("dark");

    if (document.body.classList.contains("dark")) {
        localStorage.setItem("darkMode", "enabled");
    } else {
        localStorage.setItem("darkMode", "disabled");
    }
}


/* ===============================
   Settings Panel Toggle
=============================== */
function toggleSettings() {
    const panel = document.getElementById("settingsPanel");

    if (!panel) return;

    if (panel.style.right === "0px") {
        panel.style.right = "-260px";
    } else {
        panel.style.right = "0px";
    }
}
window.addEventListener("load", () => {
    const loader = document.getElementById("loader");
    if (loader) {
        loader.style.display = "none";
    }
});
// Multi-step form
const steps=document.querySelectorAll(".form-step");
const next=document.querySelector(".next-btn");
const prev=document.querySelector(".prev-btn");
const progress=document.getElementById("progress");
let current=0;

if(next){
next.onclick=()=>{
steps[current].classList.remove("active");
current++;
steps[current].classList.add("active");
progress.style.width="100%";
};
}

if(prev){
prev.onclick=()=>{
steps[current].classList.remove("active");
current--;
steps[current].classList.add("active");
progress.style.width="50%";
};
}

// Photo preview
function previewImage(e){
const reader=new FileReader();
reader.onload=()=>{preview.src=reader.result;}
reader.readAsDataURL(e.target.files[0]);
}

// Password strength
const pass=document.getElementById("password");
const strength=document.getElementById("strength");

if(pass){
pass.onkeyup=()=>{
let val=pass.value.length;
strength.style.width=val*10+"%";
strength.style.background=val<5?"red":val<8?"orange":"lime";
};
}


const ajaxSearch = document.getElementById("jobSearch");
const jobResults = document.getElementById("jobResults");

if (ajaxSearch && jobResults) {
    ajaxSearch.addEventListener("keyup", function () {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "/victory/ajax/search-jobs.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                jobResults.innerHTML = xhr.responseText;
            }
        };
        xhr.send("keyword=" + encodeURIComponent(this.value));
    });
}
