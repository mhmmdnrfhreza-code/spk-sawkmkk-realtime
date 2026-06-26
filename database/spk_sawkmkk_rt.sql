-- =====================================================================
-- Skema basis data SPK SAW-KMKK Realtime (db: spk_sawkmkk_rt)
-- Dijalankan otomatis oleh install.php, atau impor manual via phpMyAdmin.
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS tb_penilaian;
DROP TABLE IF EXISTS tb_hasil;
DROP TABLE IF EXISTS tb_alternatif;
DROP TABLE IF EXISTS tb_kriteria;
DROP TABLE IF EXISTS tb_pakar;
DROP TABLE IF EXISTS tb_skala;
DROP TABLE IF EXISTS raw_ocds;
DROP TABLE IF EXISTS stg_kontrak;
DROP TABLE IF EXISTS mart_vendor_kriteria;
DROP TABLE IF EXISTS tb_sync_log;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE tb_skala (
  id INT PRIMARY KEY AUTO_INCREMENT,
  index_skala INT NOT NULL,
  kode VARCHAR(5),
  label VARCHAR(30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tb_kriteria (
  id INT PRIMARY KEY AUTO_INCREMENT,
  kode VARCHAR(5),
  nama VARCHAR(100),
  tipe ENUM('cost','benefit'),
  bobot_label VARCHAR(5),
  bobot_index INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tb_pakar (
  id INT PRIMARY KEY AUTO_INCREMENT,
  kode VARCHAR(5),
  nama VARCHAR(100),
  jabatan VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tb_alternatif (
  id INT PRIMARY KEY AUTO_INCREMENT,
  kode VARCHAR(10),
  nama VARCHAR(190),
  sumber ENUM('manual','ocds') DEFAULT 'ocds'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tb_penilaian (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_alt INT, id_krit INT, id_pakar INT,
  nilai_label VARCHAR(5), nilai_index INT,
  CONSTRAINT fk_pen_alt FOREIGN KEY (id_alt) REFERENCES tb_alternatif(id) ON DELETE CASCADE,
  CONSTRAINT fk_pen_krit FOREIGN KEY (id_krit) REFERENCES tb_kriteria(id) ON DELETE CASCADE,
  CONSTRAINT fk_pen_pakar FOREIGN KEY (id_pakar) REFERENCES tb_pakar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE raw_ocds (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  ocid VARCHAR(120),
  payload LONGTEXT,
  fetched_at DATETIME,
  source VARCHAR(50) DEFAULT 'find-a-tender'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stg_kontrak (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  ocid VARCHAR(120),
  supplier_name VARCHAR(200),
  amount DECIMAL(18,2),
  currency VARCHAR(10),
  tender_status VARCHAR(40),
  contract_status VARCHAR(40),
  award_date DATE NULL,
  category VARCHAR(120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mart_vendor_kriteria (
  id INT PRIMARY KEY AUTO_INCREMENT,
  supplier_name VARCHAR(200),
  median_harga DECIMAL(18,2),
  jumlah_award INT,
  rasio_selesai DECIMAL(5,2),
  last_sync DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tb_sync_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  started_at DATETIME, finished_at DATETIME,
  records_fetched INT, status VARCHAR(20), note TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tb_hasil (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_alt INT,
  supplier_name VARCHAR(200),
  p_index INT,
  p_label VARCHAR(5),
  saw_score DECIMAL(8,4),
  ranking INT,
  computed_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- SEED MASTER (Model-Driven inti) ----------
INSERT INTO tb_skala (index_skala,kode,label) VALUES
 (1,'BS','Buruk Sekali'),(2,'SK','Sangat Kurang'),(3,'K','Kurang'),(4,'Sd','Sedang'),
 (5,'B','Baik'),(6,'SB','Sangat Baik'),(7,'S','Sempurna');

INSERT INTO tb_kriteria (kode,nama,tipe,bobot_label,bobot_index) VALUES
 ('C1','Harga Alat Kesehatan','cost','S',7),
 ('C2','Kualitas / Reputasi','benefit','SB',6),
 ('C3','Layanan / Keandalan','benefit','B',5);

INSERT INTO tb_pakar (kode,nama,jabatan) VALUES
 ('PK1','Kepala Instalasi Pengadaan','Procurement Lead'),
 ('PK2','Kepala Teknik Biomedis','Biomedical Engineering Head'),
 ('PK3','Manajer Mutu','Quality Assurance Manager');
