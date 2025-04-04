<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Eskort</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="top-bar">
        <div class="top-left">
            <h2>Eskort Admin</h2>
        </div>
        <div class="top-right">
            <a href="index.php">Home</a>
            <a href="admin.php">Dashboard</a>
            <a href="logout.php">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="main-content">
            <h3>Relatórios</h3>
            <form method="GET" class="filter-form">
                <label>Data Inicial: <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"></label>
                <label>Data Final: <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"></label>
                <button type="submit" class="search-btn">Filtrar</button>
            </form>
            <canvas id="views-chart" style="max-width: 600px;"></canvas>
            <canvas id="search-chart" style="max-width: 600px;"></canvas>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetch('report_data.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>')
                .then(response => response.json())
                .then(data => {
                    const ctx = document.getElementById('views-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(item => item.name),
                            datasets: [{
                                label: 'Visualizações',
                                data: data.map(item => item.views),
                                backgroundColor: '#E95B95'
                            }]
                        },
                        options: {
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                });

            fetch('search_data.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>')
                .then(response => response.json())
                .then(data => {
                    const ctx = document.getElementById('search-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: Object.keys(data),
                            datasets: [{
                                label: 'Frequência de Buscas',
                                data: Object.values(data),
                                backgroundColor: '#E95B95'
                            }]
                        },
                        options: {
                            scales: {
                                y: { beginAtZero: true }
                            }
                        }
                    });
                });
        });
    </script>
</body>
</html>