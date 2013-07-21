# encoding: UTF-8

module OpenBudget
  module Mappers
    module CantonBern

      def from_finstabe_bfh_csv file_path
        CSV.foreach(file_path, :encoding => 'windows-1251:utf-8', :headers => true, :header_converters => :symbol, :converters => :all) do |row|
          if !@meta
            @meta = {
              name: row[:gemeinde],
              bfs_no: row[:bfsnr]
            }
          end

          account_type = row[:kontenbereich_nummer].to_i
          if [3, 4, 5, 6].include? account_type
            node = get_or_create_node \
              [row[:aufgabenbereich_nummer], row[:aufgabe_nummer], row[:aufgabenstelle_nummer]].reject(&:blank?),
              [row[:aufgabenbereich_name], row[:aufgabe_name], row[:aufgabenstelle_name]].reject(&:blank?)

            # ToDo: seperate "investitionsrechnung" [5, 6] and "laufende rechnung" [3, 4]
            if [3, 5].include? account_type
              node.add_gross_cost('accounts', row[:jahr], row[:saldo])
            elsif [4, 6].include? account_type
              node.add_revenue('accounts', row[:jahr], row[:saldo])
            end
          end
        end
      end

      def cantonbe_normalize_names names, options = {}
        # normalization and manual renaming as recommended by be.ch employees
        @cantonbe_names_overwrites ||= JSON.parse File.read('source/cantonbe/asp/massnahmen_overwrite.json')

        names[0] = @cantonbe_names_overwrites['direktion'][names[0]].presence || names[0]
        if options[:massnahme] && names[1].present?
          names[1] = @cantonbe_names_overwrites['massnahme'].fetch(names[0], {})[names[1]].presence || names[1]
        end

        names = names.dup
        names[0] = names[0].gsub(/s?(direktion)?\b/, '')

        names.each(&:strip).reject(&:blank?).collect {|id_segment|
          id_segment.downcase.gsub(/[^0-9a-z]/, ' ').gsub(/[,-]+/, ' ').gsub(/ +/, '-')
        }
      end

      def cantonbe_directorate_id normalized_name
        @cantonbe_directorate_index ||= lambda do
          directorate_meta = JSON.parse File.read('source/cantonbe/directorate_meta.json')
          index = {}
          directorate_meta.each do |directorate|
            name = cantonbe_normalize_names([directorate['name']])[0]
            index[name] = directorate['acronym'].downcase
          end
          index
        end.call

        # unless @cantonbe_directorate_index[normalized_name]
        #   p "unknown directorate #{normalized_name}"
        # end

        @cantonbe_directorate_index[normalized_name] || normalized_name
      end

      def cantonbe_names_to_ids names, options = {}
        names = cantonbe_normalize_names names, options
        names[0] = cantonbe_directorate_id names[0]

        names
      end

      def load_cantonbe_csv file_path, options = {}
        options = {
          exponent: 6
        }.merge options

        csv = CSV.read(file_path, :headers => true, :header_converters => :symbol, :converters => :all)

        number_headers = []
        csv.headers.each do |header|
          number_header = header.to_s.match(/_?(?<type>rechnung|voranschlag)_(?<year>[0-9]{4})_(?<collection>kosten|erlse).*/)
          if number_header
            number_headers << {
              method: number_header[:collection] == 'kosten' ? :add_gross_cost : :add_revenue,
              key: header,
              type: number_header[:type] == 'voranschlag' ? 'budgets' : 'accounts',
              year: number_header[:year]
            }
          end
        end

        csv.each do |row|
          names = [
            row[:direktion],
            row[:produktgruppe_zt_gekrzte_bezeichnung]
          ]
          id_path = cantonbe_names_to_ids names

          node = get_or_create_node id_path, names

          number_headers.each do |header|
            node
              .method(header[:method])
              .call(
                header[:type],
                header[:year],
                normalize_num(row[header[:key]], options)
              )
          end
        end
      end

      def load_cantonbe_asp_csv file_path
        CSV.foreach(file_path, :headers => true, :header_converters => :symbol, :converters => :all) do |row|
          names = [
            row[:direktion],
            row[:massnahme]
          ]
          id_path = cantonbe_names_to_ids names, :massnahme => true

          node = get_or_create_node id_path, names

          options = {
            comma: :decimal,
            exponent: 6
          }
          node.add_revenue('budgets', 2014, normalize_num(row[:'2014_in_mio_chf'], options))
          node.add_revenue('budgets', 2015, normalize_num(row[:'2015'], options))
          node.add_revenue('budgets', 2016, normalize_num(row[:'2016'], options))
          node.add_revenue('budgets', 2017, normalize_num(row[:'2017'], options))
        end
      end

    end
  end
end
