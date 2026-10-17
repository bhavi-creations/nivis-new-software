<!-- side bar script -->
<script>
    const sidebar = document.getElementById("mainSidebar");
    const toggle = document.getElementById("sidebarToggle");
    const overlay = document.getElementById("sidebarOverlay");

    function setSidebar(open) {
        sidebar.classList.toggle("show", open);
        overlay.classList.toggle("show", open);
        toggle.setAttribute("aria-expanded", String(open));
        toggle.setAttribute("aria-label",
            open ? "Close sidebar" : "Open sidebar");

        toggle.innerHTML = open ?
            '<i class="fa-solid fa-xmark"></i>' :
            '<i class="fa-solid fa-bars"></i>';
    }

    toggle.addEventListener("click", () => {
        setSidebar(!sidebar.classList.contains("show"));
    });

    overlay.addEventListener("click", () => setSidebar(false));

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") setSidebar(false);
    });

    document.querySelectorAll(".side-bar .nav-link")
        .forEach(link => {
            link.addEventListener("click", () => {
                if (window.innerWidth < 992) {
                    setSidebar(false);
                }
            });
        });

    // Automatically highlight current PHP page
    const currentPage =
        window.location.pathname.split("/").pop().toLowerCase();

    document.querySelectorAll(".side-bar .nav-link")
        .forEach(link => {
            const targetPage = new URL(link.href, window.location.href)
                .pathname.split("/").pop().toLowerCase();

            link.classList.toggle("active", targetPage === currentPage);
        });
</script>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/calculations.js"></script>
<script src="assets/js/database-ui.js?v=20261016-6"></script>
<script src="assets/js/ui-theme.js"></script>
</body>
</html>
