승선자 등록 PHP 버전 설치 안내

1) 카페24 서버에 폴더 업로드
   예: /www/boarding/

2) config.php 수정
   DB_HOST, DB_NAME, DB_USER, DB_PASS를 카페24 MySQL 정보로 변경
   ADMIN_PASSWORD를 원하는 관리자 비밀번호로 변경

3) DB 테이블 생성
   카페24 phpMyAdmin에서 install.sql 실행

4) 사용자 신청 주소
   https://도메인/boarding/index.php

5) 관리자 주소
   https://도메인/boarding/admin/login.php

6) 엑셀 다운로드
   관리자 목록에서 조회 조건 적용 후 '엑셀 다운로드' 클릭

참고
- 엑셀은 Cafe24 환경 호환성을 위해 .xls HTML 방식으로 만들었습니다.
- 한글 깨짐 방지를 위해 UTF-8 BOM을 포함했습니다.
- applications 삭제 시 passengers는 외래키 ON DELETE CASCADE로 함께 삭제됩니다.
