</div> <!-- End of container -->

<!-- Bottom Navigation -->
<div class="bottom-nav">
    <a href="index.php" class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
        <i data-lucide="home"></i>
        <span>Home</span>
    </a>
    <a href="projects.php" class="nav-item <?php echo $current_page == 'projects.php' ? 'active' : ''; ?>">
        <i data-lucide="building-2"></i>
        <span>Projects</span>
    </a>
    
    <div class="fab-container">
        <!-- Floating button to Add Lead or Share Link -->
        <a href="marketing.php" class="fab">
            <i data-lucide="share-2" style="width: 24px; height: 24px;"></i>
        </a>
    </div>
    
    <a href="clients.php" class="nav-item <?php echo $current_page == 'clients.php' ? 'active' : ''; ?>">
        <i data-lucide="users"></i>
        <span>Clients</span>
    </a>
    <a href="payouts.php" class="nav-item <?php echo $current_page == 'payouts.php' ? 'active' : ''; ?>">
        <i data-lucide="wallet"></i>
        <span>Payouts</span>
    </a>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>
