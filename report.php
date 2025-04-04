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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
            <button onclick="exportChart('views-chart', 'Visualizações')" class="export-btn">Exportar PNG (Views)</button>
            <button onclick="exportChart('search-chart', 'Buscas')" class="export-btn">Exportar PNG (Buscas)</button>
            <button onclick="exportChart('ratings-chart', 'Avaliações')" class="export-btn">Exportar PNG (Ratings)</button>
            <button onclick="exportExcel()" class="export-btn">Exportar Excel</button>
            <canvas id="views-chart" style="max-width: 600px;"></canvas>
            <canvas id="search-chart" style="max-width: 600px;"></canvas>
            <canvas id="ratings-chart" style="max-width: 600px;"></canvas>
        </div>
    </div>

    <script>
        let viewsData, searchData, ratingsData;

        document.addEventListener('DOMContentLoaded', () => {
            fetch('report_data.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>')
                .then(response => response.json())
                .then(data => {
                    viewsData = data;
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
                            scales: { y: { beginAtZero: true } },
                            plugins: { zoom: { zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'xy' } } },
                            onClick: (e, elements) => {
                                if (elements.length > 0) {
                                    const index = elements[0].index;
                                    alert(`Perfil: ${data[index].name}\nVisualizações: ${data[index].views}`);
                                }
                            }
                        }
                    });
                });

            fetch('search_data.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>')
                .then(response => response.json())
                .then(data => {
                    searchData = data;
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
                            scales: { y: { beginAtZero: true } },
                            plugins: { zoom: { zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'xy' } } },
                            onClick: (e, elements) => {
                                if (elements.length > 0) {
                                    const index = elements[0].index;
                                    const term = Object.keys(data)[index];
                                    alert(`Termo: ${term}\nFrequência: ${data[term]}`);
                                }
                            }
                        }
                    });
                });

            fetch('ratings_data.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>')
                .then(response => response.json())
                .then(data => {
                    ratingsData = data;
                    const ctx = document.getElementById('ratings-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(item => item.name),
                            datasets: [{
                                label: 'Média de Avaliações',
                                data: data.map(item => item.avg_rating),
                                backgroundColor: '#E95B95'
                            }]
                        },
                        options: {
                            scales: { y: { beginAtZero: true, max: 5 } },
                            plugins: { zoom: { zoom: { wheel: { enabled: true }, pinch: { enabled: true }, mode: 'xy' } } },
                            onClick: (e, elements) => {
                                if (elements.length > 0) {
                                    const index = elements[0].index;
                                    alert(`Perfil: ${data[index].name}\nMédia: ${data[index].avg_rating}\nTotal: ${data[index].total_reviews}`);
                                }
                            }
                        }
                    });
                });
        });

        function exportChart(chartId, title) {
            const canvas = document.getElementById(chartId);
            const link = document.createElement('a');
            link.href = canvas.toDataURL('image/png');
            link.download = `${title}_${new Date().toISOString().slice(0,10)}.png`;
            link.click();
        }

        function exportExcel() {
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(viewsData), 'Visualizações');
            const searchArray = Object.entries(searchData).map(([query, count]) => ({ Query: query, Count: count }));
            XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(searchArray), 'Buscas');
            XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(ratingsData), 'Avaliações');
            XLSX.writeFile(wb, `relatorio_${new Date().toISOString().slice(0,10)}.xlsx`);
        }
    </script>
</body>
</html>