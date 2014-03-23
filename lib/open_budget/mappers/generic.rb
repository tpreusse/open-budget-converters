module OpenBudget
  module Mappers
    module Generic
      def load_csv file_path
        csv = CSV.read(file_path, :headers => true, skip_blanks: true)

        levels = []
        value_headers = []
        csv.headers.each do |header|
          level_id_header = header.to_s.match(/level_(?<index>[0-9]+)_(id|name)/)
          if level_id_header
            levels << level_id_header[:index]
          end
          value_header = header.to_s.match(/value_(?<collection>.+)_(?<type>.+)_(?<year>[0-9]{4})/)
          if value_header
            value_headers << {
              key: header,
              collection: value_header[:collection],
              type: value_header[:type],
              year: value_header[:year]
            }
          end
        end

        levels.uniq.sort!

        csv.each do |row|
          ids = []
          names = []
          levels.each do |level|
            name = row["level_#{level}_name"]
            id = row["level_#{level}_id"].presence || name_to_id(name)

            break unless id.present? || name.present?

            ids << id
            names << name
          end

          # blank row
          if row.to_s.gsub(/^,+$/,'').blank?
            next
          end
          # id less row
          if ids.compact.blank?
            puts "skipping row without ids #{row}"
            next
          end
          # ToDo: check for duplicate nodes
          node = get_or_create_node ids, names

          value_headers.each do |header|
            node.add(
              header[:collection],
              header[:type],
              header[:year],
              normalize_num(row[header[:key]], comma: :decimal)
            )
          end
        end
      end

      def name_to_id name
        return unless name
        name.to_s.mb_chars.normalize(:kd).gsub(/[^\x00-\x7F]/,'').downcase.gsub(/[^0-9a-z]/, ' ').gsub(/[,-]+/, ' ').strip.gsub(/ +/, '-').to_s
      end
    end
  end
end
