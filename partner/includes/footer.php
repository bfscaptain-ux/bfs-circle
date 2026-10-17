</div> <!-- End of container -->

<div class="bottom-nav">
    <?php $current = basename($_SERVER['PHP_SELF']); ?>
    
    <a href="index.php" class="nav-item <?php echo ($current == 'index.php') ? 'active' : ''; ?>">
        <i data-lucide="home"></i>
        <span>Home</span>
    </a>
    
    <a href="clients.php" class="nav-item <?php echo ($current == 'clients.php') ? 'active' : ''; ?>">
        <i data-lucide="briefcase"></i>
        <span>Portfolio</span>
    </a>
    
    <div class="fab-wrapper">
        <a href="add_client.php" class="fab">
            <i data-lucide="plus"></i>
        </a>
    </div>
    
    <a href="tools.php" class="nav-item <?php echo ($current == 'tools.php') ? 'active' : ''; ?>">
        <i data-lucide="calculator"></i>
        <span>Tools</span>
    </a>
    
    <a href="leaderboard.php" class="nav-item <?php echo ($current == 'leaderboard.php') ? 'active' : ''; ?>">
        <i data-lucide="award"></i>
        <span>Rank</span>
    </a>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>
